<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Faking\MockResponse;

mutates(SteamManager::class);

it('attaches the steam fake it returns', function (): void {
    $fake = Steam::fake();

    expect(Steam::connector()->getMockClient())->toBe($fake);
});

it('fakes a whole news feed through the chain', function (): void {
    Steam::fake()->news()->newsFeed(...newsFeedItems(3));

    expect(Steam::newsFeed(appId: 440, perPage: 2)->pluck('id')->all())->toBe(['1', '2', '3']);

    Steam::assertSentCount(2);
});

it('chains from the array form', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => MockResponse::make([
            'response' => ['success' => 1, 'steamid' => '76561198000000000'],
        ]),
    ])->players()->steamLevel(42);

    expect(Steam::users()->resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000')
        ->and(Steam::players()->steamLevel(steamId()))->toBe(42);
});

it('fails every request without its own response', function (Closure $fail, string $exception): void {
    $fake = Steam::fake()->players()->steamLevel(42);

    expect($fail($fake))->toBe($fake)
        ->and(Steam::players()->steamLevel(steamId()))->toBe(42)
        ->and(fn (): array => Steam::users()->summaries([steamId()]))->toThrow($exception);
})->with([
    'invalid api key' => [
        fn (SteamFake $fake): SteamFake => $fake->invalidApiKey(),
        InvalidApiKeyException::class,
    ],
    'connection failed' => [
        fn (SteamFake $fake): SteamFake => $fake->connectionFailed(),
        SteamConnectionException::class,
    ],
    'too many requests' => [
        fn (SteamFake $fake): SteamFake => $fake->tooManyRequests(),
        SteamRateLimitException::class,
    ],
]);
