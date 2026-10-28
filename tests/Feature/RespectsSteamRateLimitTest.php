<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Queue\Middleware\RespectsSteamRateLimit;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerSummaryFactory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\FakeJob;

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

it('runs the job while the budget has room', function (): void {
    fakeSteamEndpoints();

    $job = queuedJob();

    $result = new RespectsSteamRateLimit()->handle($job, fn (): string => Steam::users()->resolveVanityUrl('gabe')->value);

    expect($result)->toBe(steamId()->value);

    $job->assertNotReleased();
});

it('releases the job for what is left of a spent daily budget, sending nothing', function (): void {
    spendDailyBudget();

    Steam::fake()->users()->summaries(PlayerSummaryFactory::new());

    $job = queuedJob();
    $ran = false;

    new RespectsSteamRateLimit()->handle($job, function () use (&$ran): void {
        $ran = true;

        Steam::users()->summaries([steamId()]);
    });

    // The base takes midnight from a native DateTimeImmutable, which travelTo() does not reach.
    $untilMidnight = new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))->getTimestamp() - time();

    expect($ran)->toBeFalse()
        ->and(releaseDelay($job))->toBeGreaterThanOrEqual($untilMidnight - 1)->toBeLessThanOrEqual($untilMidnight + 1);

    Steam::assertNothingSent();
});

it('releases the job for what is left of a configured window', function (): void {
    config()->set('steam-api.rate_limit.limits', [['allow' => 1, 'every' => 60]]);

    fakeSteamEndpoints();

    new RespectsSteamRateLimit()->handle(queuedJob(), fn (): string => Steam::users()->resolveVanityUrl('gabe')->value);

    $job = queuedJob();

    new RespectsSteamRateLimit()->handle($job, fn (): string => Steam::users()->resolveVanityUrl('gabe')->value);

    expect(releaseDelay($job))->toBeGreaterThanOrEqual(59)->toBeLessThanOrEqual(60);

    Steam::assertSentCount(1);
});

it('releases the job for the window a 429 names', function (?string $key): void {
    config()->set('steam-api.key', $key);

    Steam::fake()->tooManyRequests(120);

    $job = queuedJob();

    new RespectsSteamRateLimit()->handle($job, fn (): int => Steam::stats()->currentPlayers(440));

    expect(releaseDelay($job))->toBeGreaterThanOrEqual(119)->toBeLessThanOrEqual(120);
})->with([
    'with a key' => 'test-steam-api-key',
    'without one' => null,
]);

it('holds the next job back for the rest of a 429 window', function (): void {
    config()->set('steam-api.key');

    Steam::fake()->tooManyRequests(120);

    new RespectsSteamRateLimit()->handle(queuedJob(), fn (): int => Steam::stats()->currentPlayers(440));

    $job = queuedJob();
    $ran = false;

    new RespectsSteamRateLimit()->handle($job, function () use (&$ran): void {
        $ran = true;

        Steam::stats()->currentPlayers(440);
    });

    expect($ran)->toBeFalse()
        ->and(releaseDelay($job))->toBeGreaterThanOrEqual(119)->toBeLessThanOrEqual(120);

    Steam::assertSentCount(1);
});

it('runs the next job under a key and releases it at the keyless request the limiter refuses', function (): void {
    Steam::fake()->tooManyRequests(120);

    new RespectsSteamRateLimit()->handle(queuedJob(), fn (): int => Steam::stats()->currentPlayers(440));

    $job = queuedJob();
    $ran = false;

    new RespectsSteamRateLimit()->handle($job, function () use (&$ran): void {
        $ran = true;

        Steam::stats()->currentPlayers(440);
    });

    expect($ran)->toBeTrue()
        ->and(releaseDelay($job))->toBeGreaterThanOrEqual(119)->toBeLessThanOrEqual(120);

    Steam::assertSentCount(1);
});

it('floors a window that already closed at a second', function (): void {
    $job = queuedJob();

    new RespectsSteamRateLimit()->handle($job, function (): never {
        throw closedRateLimitWindow();
    });

    $job->assertReleased(delay: 1);
});

it('lets every other steam failure through', function (): void {
    Steam::fake()->users()->groupsNotPublic();

    $job = queuedJob();

    expect(fn (): mixed => new RespectsSteamRateLimit()->handle($job, fn (): array => Steam::users()->groups(steamId())))
        ->toThrow(ProfileNotPublicException::class);

    $job->assertNotReleased();
});

it('leaves a job it cannot release to fail as it would without the middleware', function (): void {
    spendDailyBudget();

    Steam::fake()->users()->summaries(PlayerSummaryFactory::new());

    $ran = false;

    $next = function () use (&$ran): array {
        $ran = true;

        return Steam::users()->summaries([steamId()]);
    };

    expect(fn (): mixed => new RespectsSteamRateLimit()->handle(new stdClass, $next))
        ->toThrow(SteamRateLimitException::class)
        ->and($ran)->toBeTrue();

    Steam::assertNothingSent();
});
