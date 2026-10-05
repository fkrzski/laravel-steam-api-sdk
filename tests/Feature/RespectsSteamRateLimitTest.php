<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Queue\Middleware\RespectsSteamRateLimit;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\FakeJob;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Limit;

mutates(RespectsSteamRateLimit::class);

final class QueuedSteamJob
{
    use InteractsWithQueue;
}

function queuedJob(): QueuedSteamJob
{
    return new QueuedSteamJob()->withFakeQueueInteractions();
}

/**
 * The delay the job was released for, read off the fake rather than asserted
 * exactly: the window closes on the wall clock, so it may lose a second.
 */
function releaseDelay(QueuedSteamJob $job): int
{
    if (! $job->job instanceof FakeJob) {
        throw new LogicException('Queue interactions are not faked.');
    }

    return $job->job->releaseDelay;
}

/**
 * Write a spent daily budget into the store the connector reads, as a day of
 * traffic would.
 */
function spendDailyBudget(): void
{
    $connector = Steam::connector();

    $limit = array_find(
        $connector->getLimits(),
        static fn (Limit $limit): bool => ! $limit->usesResponse(),
    );

    $limit?->hit(100_000)->save($connector->rateLimitStore());
}

function throttleCurrentPlayers(): void
{
    Steam::fake([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
    ]);
}

it('runs the job while the budget has room', function (): void {
    fakeSteamEndpoints();

    $job = queuedJob();

    $result = new RespectsSteamRateLimit()->handle($job, fn (): string => Steam::resolveVanityUrl('gabe')->value);

    expect($result)->toBe(steamId()->value);

    $job->assertNotReleased();
});

it('releases the job for what is left of a spent daily budget, sending nothing', function (): void {
    spendDailyBudget();

    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(PlayerSummaryFactory::new()),
    ]);

    $job = queuedJob();
    $ran = false;

    new RespectsSteamRateLimit()->handle($job, function () use (&$ran): void {
        $ran = true;

        Steam::summaries([steamId()]);
    });

    expect($ran)->toBeFalse()
        ->and(releaseDelay($job))->toBeGreaterThanOrEqual(86_399)->toBeLessThanOrEqual(86_400);

    Steam::assertNothingSent();
});

it('releases the job for the window a 429 names', function (?string $key): void {
    config()->set('steam-api.key', $key);

    throttleCurrentPlayers();

    $job = queuedJob();

    new RespectsSteamRateLimit()->handle($job, fn (): int => Steam::currentPlayers(440));

    expect(releaseDelay($job))->toBeGreaterThanOrEqual(119)->toBeLessThanOrEqual(120);
})->with([
    'with a key' => 'test-steam-api-key',
    'without one' => null,
]);

it('holds the next job back for the rest of a 429 window', function (?string $key): void {
    config()->set('steam-api.key', $key);

    throttleCurrentPlayers();

    new RespectsSteamRateLimit()->handle(queuedJob(), fn (): int => Steam::currentPlayers(440));

    $job = queuedJob();
    $ran = false;

    new RespectsSteamRateLimit()->handle($job, function () use (&$ran): void {
        $ran = true;

        Steam::currentPlayers(440);
    });

    expect($ran)->toBeFalse()
        ->and(releaseDelay($job))->toBeGreaterThanOrEqual(119)->toBeLessThanOrEqual(120);

    Steam::assertSentCount(1);
})->with([
    'with a key' => 'test-steam-api-key',
    'without one' => null,
]);

it('floors a window that already closed at a second', function (): void {
    $job = queuedJob();

    new RespectsSteamRateLimit()->handle($job, function (): never {
        throw SteamRateLimitException::fromLimit(
            Limit::allow(1)->everySeconds(60)->setExpiryTimestamp(time() - 30),
        );
    });

    $job->assertReleased(delay: 1);
});

it('lets every other steam failure through', function (): void {
    Steam::fake([GetUserGroupListRequest::class => SteamResponse::profileNotPublic()]);

    $job = queuedJob();

    expect(fn (): mixed => new RespectsSteamRateLimit()->handle($job, fn (): array => Steam::groups(steamId())))
        ->toThrow(ProfileNotPublicException::class);

    $job->assertNotReleased();
});

it('leaves a job it cannot release to fail as it would without the middleware', function (): void {
    spendDailyBudget();

    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(PlayerSummaryFactory::new()),
    ]);

    $ran = false;

    $next = function () use (&$ran): array {
        $ran = true;

        return Steam::summaries([steamId()]);
    };

    expect(fn (): mixed => new RespectsSteamRateLimit()->handle(new stdClass, $next))
        ->toThrow(SteamRateLimitException::class)
        ->and($ran)->toBeTrue();

    Steam::assertNothingSent();
});
