<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Contracts\SteamManager as SteamManagerContract;
use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeOutsideTestsException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GameSchemaFactory;
use Illuminate\Support\LazyCollection;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;

mutates(SteamManager::class);

it('resolves the connector and manager once per container', function (): void {
    expect(app(SteamConnector::class))->toBe(app(SteamConnector::class))
        ->and(app(SteamManager::class))->toBe(app(SteamManager::class));
});

it('binds the manager contract to the concrete instance', function (): void {
    expect(app(SteamManagerContract::class))->toBe(app(SteamManager::class));
});

it('rebuilds the connector once scoped instances are flushed', function (): void {
    $connector = app(SteamConnector::class);

    app()->forgetScopedInstances();

    expect(app(SteamConnector::class))->not->toBe($connector);
});

// Mirrors Octane, which serves each request from a shallow clone of the app.
it('resolves the connector from the container that built the manager', function (): void {
    $sandbox = clone app();
    $sandbox->forgetScopedInstances();

    $manager = $sandbox->make(SteamManager::class);

    expect($manager->connector())->toBe($sandbox->make(SteamConnector::class))
        ->and($manager->connector())->not->toBe(app(SteamConnector::class));
});

it('merges the package config', function (): void {
    expect(config('steam-api.exceptions.render'))->toBeTrue();
});

it('sends the configured key with a request that needs one', function (): void {
    Steam::fake()->users()->summaries();

    Steam::users()->summaries([steamId()]);

    expect(Steam::lastResponse()?->getPendingRequest()->query()->all())->toHaveKey('key', 'test-steam-api-key');
});

it('sends the configured language with a localised request', function (): void {
    config()->set('steam-api.language', 'polish');

    Steam::fake()->stats()->schema(GameSchemaFactory::new());

    Steam::stats()->schema(appId: 620);

    expect(Steam::lastResponse()?->getPendingRequest()->query()->all())->toHaveKey('l', 'polish');
});

it('sends an anonymous request with no key configured', function (): void {
    config()->set(['steam-api.key' => null]);

    Steam::fake()->stats()->currentPlayers(12_345);

    Steam::stats()->currentPlayers(appId: 381210);

    expect(Steam::lastResponse()?->getPendingRequest()->query()->all())->not->toHaveKey('key');

    Steam::assertSent(GetNumberOfCurrentPlayersRequest::class);
});

it('throws when a request needing the key is sent without one', function (): void {
    config()->set(['steam-api.key' => null]);

    Steam::fake()->users()->summaries();

    expect(fn (): array => Steam::users()->summaries([steamId()]))
        ->toThrow(ApiKeyNotConfiguredException::class);

    Steam::assertNothingSent();
});

it('resolves the facade to the manager singleton', function (): void {
    expect(Steam::getFacadeRoot())->toBe(app(SteamManager::class));
});

it('resolves the facade through the manager contract', function (): void {
    $replacement = new SteamManager(fn (): SteamConnector => app(SteamConnector::class), app());

    app()->instance(SteamManagerContract::class, $replacement);

    expect(Steam::getFacadeRoot())->toBe($replacement);
});

it('exposes the underlying connector', function (): void {
    expect(Steam::connector())->toBe(app(SteamConnector::class));
});

it('does not leak the fake past the scope that installed it', function (): void {
    Steam::fake()->users()->resolveVanityUrl(steamId());

    // Read through a variable: expecting on the same call twice has PHPStan
    // carry the first result past forgetScopedInstances().
    $fakedInScope = app(SteamConnector::class)->hasMockClient();

    app()->forgetScopedInstances();

    expect($fakedInScope)->toBeTrue()
        ->and(app(SteamConnector::class)->hasMockClient())->toBeFalse();
});

it('keeps the configured tries on a fake but not the pause between them', function (): void {
    config()->set([
        'steam-api.http.retry.tries' => 3,
        'steam-api.http.retry.interval' => 1000,
    ]);

    Steam::fake([
        GetPlayerSummariesRequest::class => MockResponse::make([], 503),
    ]);

    expect(fn (): array => Steam::users()->summaries([steamId()]))
        ->toThrow(SteamApiException::class, 'HTTP 503');

    Steam::assertSentCount(3);

    expect(Steam::connector()->retryInterval)->toBe(0)
        ->and(Steam::connector()->tries)->toBe(3);
});

it('refuses to fake outside the testing environment', function (): void {
    app()->instance('env', 'production');

    expect(fn (): MockClient => Steam::fake())
        ->toThrow(FakeOutsideTestsException::class, 'only available while running tests');
});

it("walks an app's news a page at a time", function (): void {
    $items = newsFeedItems(5);

    Steam::fake()->news()->newsFeed(...$items);

    expect(Steam::newsFeed(appId: 440, perPage: 2)->pluck('id')->all())->toBe(['1', '2', '3', '4', '5']);

    $page = static fn (?DateTimeInterface $endDate): Closure => static fn (GetNewsForAppRequest $request): bool => $request->count === 2
        && $request->endDate?->getTimestamp() === $endDate?->getTimestamp();

    Steam::assertSentInOrder([
        $page(null),
        $page($items[1]->make()->publishedAt),
        $page($items[2]->make()->publishedAt),
        $page($items[3]->make()->publishedAt),
    ]);
});

it('passes the filters and the starting date to every page', function (): void {
    $items = newsFeedItems(3);

    Steam::fake()->news()->newsFeed(...$items);

    $endDate = new DateTimeImmutable('2026-10-01 12:00:00 UTC');

    Steam::newsFeed(
        appId: 440,
        perPage: 2,
        maxLength: 100,
        endDate: $endDate,
        feeds: ['tf2_blog'],
        tags: ['patchnotes'],
    )->all();

    $filtered = static fn (GetNewsForAppRequest $request): bool => $request->appId === 440
        && $request->count === 2
        && $request->maxLength === 100
        && $request->feeds === ['tf2_blog']
        && $request->tags === ['patchnotes'];

    Steam::assertSentInOrder([
        static fn (GetNewsForAppRequest $request): bool => $filtered($request) && $request->endDate === $endDate,
        static fn (GetNewsForAppRequest $request): bool => $filtered($request)
            && $request->endDate?->getTimestamp() === $items[1]->make()->publishedAt->getTimestamp(),
    ]);
});

it('keeps every item sharing a second with the page boundary', function (): void {
    $second = new DateTimeImmutable('2026-10-01 10:00:00 UTC');

    Steam::fake()->news()->newsFeed(
        NewsItemFactory::new()->id('c')->publishedAt($second->modify('+1 hour')),
        NewsItemFactory::new()->id('b')->publishedAt($second),
        NewsItemFactory::new()->id('a')->publishedAt($second),
        NewsItemFactory::new()->id('z')->publishedAt($second->modify('-1 hour')),
    );

    expect(Steam::newsFeed(appId: 440, perPage: 3)->pluck('id')->all())->toBe(['c', 'b', 'a', 'z']);

    Steam::assertSentCount(2);
});

it('stops sending once the collection has enough', function (): void {
    Steam::fake()->news()->newsFeed(...newsFeedItems(5));

    expect(Steam::newsFeed(appId: 440, perPage: 2)->take(3)->pluck('id')->all())->toBe(['1', '2', '3']);

    Steam::assertSentCount(2);
});

it('sends nothing until the feed is walked', function (): void {
    Steam::fake()->news()->newsFeed(...newsFeedItems(3));

    $feed = Steam::newsFeed(appId: 440);

    Steam::assertNothingSent();

    expect($feed->count())->toBe(3);
});

it('ends on a page holding everything left', function (): void {
    Steam::fake()->news()->newsFeed(...newsFeedItems(2));

    expect(Steam::newsFeed(appId: 440)->pluck('id')->all())->toBe(['1', '2']);

    Steam::assertSentInOrder([
        static fn (GetNewsForAppRequest $request): bool => $request->count === 20,
    ]);
});

// Steam pages past a second only when one page holds every item in it.
it('ends on a page that brings nothing new', function (): void {
    Steam::fake()->news()->newsFeed(
        NewsItemFactory::new()->id('1'),
        NewsItemFactory::new()->id('2'),
        NewsItemFactory::new()->id('3'),
    );

    expect(Steam::newsFeed(appId: 440, perPage: 2)->pluck('id')->all())->toBe(['1', '2']);

    Steam::assertSentCount(2);
});

it('yields nothing for an app without news', function (): void {
    Steam::fake()->news()->newsFeed();

    expect(Steam::newsFeed(appId: 440)->all())->toBeEmpty();

    Steam::assertSentCount(1);
});

it('refuses a page smaller than two items', function (): void {
    Steam::fake();

    expect(fn (): LazyCollection => Steam::newsFeed(appId: 440, perPage: 1))->toThrow(
        InvalidArgumentException::class,
        'newsFeed() needs a page of at least 2 items: every page after the first opens with the last item of the one before.',
    );

    Steam::assertNothingSent();
});

it('raises unavailable news once the feed is walked', function (): void {
    Steam::fake()->news()->appNewsUnavailable();

    $feed = Steam::newsFeed(appId: 480);

    expect(fn (): array => $feed->all())
        ->toThrow(AppNewsUnavailableException::class, 'GetNewsForApp: Steam returned no news for app 480');
});

it('sends an arbitrary request and returns the raw response', function (): void {
    Steam::fake()->users()->resolveVanityUrl(steamId());

    $response = Steam::send(new ResolveVanityUrlRequest('gabelogannewell'));

    expect($response->status())->toBe(200);
});

it('sends requests concurrently through a pool', function (): void {
    Steam::fake()->users()->summaries();

    $sent = [];

    Steam::pool(
        requests: [new GetPlayerSummariesRequest([steamId()])],
        concurrency: 1,
        responseHandler: function (Response $response) use (&$sent): void {
            $sent[] = $response->status();
        },
    )->send()->wait();

    expect($sent)->toBe([200]);
});
