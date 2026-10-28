<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamRateLimitOptionException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\RateLimitOptions;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\UtcDayLimit;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;

mutates(RateLimitOptions::class, InvalidSteamRateLimitOptionException::class, UtcDayLimit::class);

function rateLimitOptions(): RateLimitOptions
{
    return app(RateLimitOptions::class);
}

function configuredLimit(int|string $key = 0): Limit
{
    return rateLimitOptions()->limits()[$key] ?? throw new LogicException(sprintf('No limit is configured under %s.', $key));
}

/**
 * Send one faked request, so the plugin saves the daily counter, and return
 * the key it saved it under.
 */
function sendCountedRequest(): string
{
    fakeSteamEndpoints();

    Steam::users()->resolveVanityUrl('gabelogannewell');

    $limit = array_find(Steam::connector()->getLimits(), static fn (Limit $limit): bool => ! $limit->usesResponse());

    return $limit?->getName() ?? throw new LogicException('The connector declares no daily limit.');
}

/**
 * The limit a Steam call was refused for.
 */
function refusedLimit(Closure $call): Limit
{
    try {
        $call();
    } catch (SteamRateLimitException $steamRateLimitException) {
        return $steamRateLimitException->limit;
    }

    throw new LogicException('Expected the call to be refused.');
}

/**
 * Build the connector again from these limits, as the next scope would, over
 * the counters the last one saved.
 *
 * @param  array<mixed>|null  $limits
 */
function rebuildWithLimits(?array $limits): void
{
    config()->set('steam-api.rate_limit.limits', $limits);

    app()->forgetScopedInstances();

    fakeSteamEndpoints();
}

function useSteamRateLimitStore(): void
{
    config()->set([
        'cache.stores.steam' => ['driver' => 'array'],
        'steam-api.rate_limit.store' => 'steam',
    ]);
}

it('keeps the counter in the default store until told otherwise', function (): void {
    $key = sendCountedRequest();

    expect(Cache::store()->get($key))->toBeString();
});

it('keeps the counter in the configured store', function (): void {
    useSteamRateLimitStore();

    $key = sendCountedRequest();

    expect(Cache::store('steam')->get($key))->toBeString()
        ->and(Cache::store()->get($key))->toBeNull();
});

it('keeps the counter in the configured store through a cache clear', function (): void {
    useSteamRateLimitStore();

    $key = sendCountedRequest();

    $this->pendingCommand('cache:clear')->assertSuccessful();

    expect(Cache::store('steam')->get($key))->toBeString();
});

it('reads the trimmed store name', function (): void {
    config()->set([
        'cache.stores.steam' => ['driver' => 'array'],
        'steam-api.rate_limit.store' => '  steam  ',
    ]);

    expect(rateLimitOptions()->store())->toBe('steam');
});

it('falls back to the default store for a store left unset', function (mixed $store): void {
    config()->set('steam-api.rate_limit.store', $store);

    expect(rateLimitOptions()->store())->toBeNull();
})->with([
    'null' => null,
    'empty string' => '',
    'whitespace only' => '   ',
]);

it('falls back to the defaults when the published config drops the options', function (): void {
    config()->set('steam-api.rate_limit', []);

    expect(rateLimitOptions()->store())->toBeNull()
        ->and(rateLimitOptions()->limits())->toBeNull()
        ->and(rateLimitOptions()->cooldown())->toBe(60);
});

it('throws when the cache config defines no such store', function (mixed $store): void {
    config()->set('steam-api.rate_limit.store', $store);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamRateLimitOptionException::class, '"steam-api.rate_limit.store"');
})->with([
    'an undefined name' => 'redis-x',
    'the null store' => 'null',
    'a number' => 123,
    'a boolean' => true,
    'an array' => [[]],
]);

it('keeps the base sdk daily budget while limits are unset', function (): void {
    expect(rateLimitOptions()->limits())->toBeNull()
        ->and(sendCountedRequest())->toEndWith(':100000_every_utc_midnight');
});

it('lets requests past a spent daily budget once limits are empty', function (): void {
    spendDailyBudget();

    rebuildWithLimits([]);

    Steam::users()->resolveVanityUrl('gabelogannewell');

    Steam::assertSentCount(1);
});

it('refuses a request over a configured limit before sending it', function (): void {
    config()->set('steam-api.rate_limit.limits', [['allow' => 1, 'every' => 60]]);

    fakeSteamEndpoints();

    Steam::users()->resolveVanityUrl('gabelogannewell');

    $limit = refusedLimit(fn (): string => Steam::users()->resolveVanityUrl('gabelogannewell')->value);

    expect($limit->getName())->toEndWith(':1_every_60');

    Steam::assertSentCount(1);
});

it('meters a connector without an api key once limits are configured', function (): void {
    config()->set([
        'steam-api.key' => null,
        'steam-api.rate_limit.limits' => [['allow' => 1, 'every' => 60]],
    ]);

    Steam::fake()->stats()->currentPlayers(12_345);

    Steam::stats()->currentPlayers(440);

    $limit = refusedLimit(fn (): int => Steam::stats()->currentPlayers(440));

    expect($limit->getName())->toBe('SteamConnector:anonymous:1_every_60');
});

it('shares the default daily counter with a day window', function (): void {
    spendDailyBudget();

    rebuildWithLimits([['allow' => 100_000, 'every' => 'day']]);

    expect(fn (): string => Steam::users()->resolveVanityUrl('gabelogannewell')->value)
        ->toThrow(SteamRateLimitException::class);

    Steam::assertNothingSent();
});

it('counts a day window to the next 00:00 UTC whenever it opens', function (): void {
    config()->set('steam-api.rate_limit.limits', [['allow' => 10, 'every' => 'day']]);

    $timezone = date_default_timezone_get();

    // Midnight there is never midnight in UTC, so a window taking the default zone would show.
    date_default_timezone_set('Pacific/Kiritimati');

    try {
        $expiry = configuredLimit()->resetLimit()->getExpiryTimestamp();
    } finally {
        date_default_timezone_set($timezone);
    }

    expect($expiry)->toBe(new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))->getTimestamp());
});

it('keeps the window the store holds for a day limit', function (): void {
    $store = new LaravelCacheStore(Cache::store());
    $limit = UtcDayLimit::requests(10);

    $store->set($limit->getName(), json_encode(['timestamp' => time() + 3600, 'hits' => 4], JSON_THROW_ON_ERROR), 3600);

    $limit->update($store);

    expect($limit->getHits())->toBe(4)
        ->and($limit->getRemainingSeconds())->toBeGreaterThanOrEqual(3599)->toBeLessThanOrEqual(3600);
});

it('builds a window in seconds and one reset at 00:00 UTC', function (): void {
    config()->set('steam-api.rate_limit.limits', [
        'rolling' => ['allow' => 500, 'every' => 86_400],
        'daily' => ['allow' => 500, 'every' => 'day'],
    ]);

    expect(configuredLimit('rolling'))->not->toBeInstanceOf(UtcDayLimit::class)
        ->and(configuredLimit('rolling')->getReleaseInSeconds())->toBe(86_400)
        ->and(configuredLimit('daily'))->toBeInstanceOf(UtcDayLimit::class)
        ->and(configuredLimit('daily')->getReleaseInSeconds())->toBe(86_400)
        ->and(configuredLimit('daily')->getAllow())->toBe(500);
});

it('reads numbers and booleans the way env() hands them over', function (): void {
    config()->set('steam-api.rate_limit.limits', [['allow' => '10', 'every' => ' 60 ', 'sleep' => 'true']]);

    $limit = configuredLimit();

    expect($limit->getAllow())->toBe(10)
        ->and($limit->getReleaseInSeconds())->toBe(60)
        ->and($limit->getShouldSleep())->toBeTrue();
});

it('waits the window out when told to sleep', function (int|string $every): void {
    config()->set('steam-api.rate_limit.limits', [['allow' => 10, 'every' => $every, 'sleep' => true]]);

    expect(configuredLimit()->getShouldSleep())->toBeTrue();
})->with([
    'a window in seconds' => 1,
    'a day' => 'day',
]);

it('throws rather than sleeps unless told otherwise', function (array $entry): void {
    config()->set('steam-api.rate_limit.limits', [['allow' => 10, 'every' => 60, ...$entry]]);

    expect(configuredLimit()->getShouldSleep())->toBeFalse();
})->with([
    'left out' => [[]],
    'null' => [['sleep' => null]],
    'empty string' => [['sleep' => '']],
    'false' => [['sleep' => false]],
    'false as a string' => [['sleep' => 'false']],
]);

it('rejects a limit it cannot build, naming the config key', function (mixed $limits, string $message): void {
    config()->set('steam-api.rate_limit.limits', $limits);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamRateLimitOptionException::class, $message);
})->with([
    'limits not a list' => ['ten', '"steam-api.rate_limit.limits" cannot be "ten".'],
    'an entry not an array' => [[5], '"steam-api.rate_limit.limits.0" cannot be 5.'],
    'an unknown key' => [[['allow' => 1, 'evry' => 60, 'slep' => true]], '"steam-api.rate_limit.limits.0.evry" cannot be 60.'],
    'allow left out' => [[['every' => 60]], '"steam-api.rate_limit.limits.0.allow" cannot be null.'],
    'allow zero' => [[['allow' => 0, 'every' => 60]], '"steam-api.rate_limit.limits.0.allow" cannot be 0.'],
    'allow not a number' => [[['allow' => 'ten', 'every' => 60]], '"steam-api.rate_limit.limits.0.allow" cannot be "ten".'],
    'allow a boolean' => [[['allow' => true, 'every' => 60]], '"steam-api.rate_limit.limits.0.allow" cannot be true.'],
    'allow a fraction' => [[['allow' => 1.5, 'every' => 60]], '"steam-api.rate_limit.limits.0.allow" cannot be 1.5.'],
    'allow blank' => [[['allow' => '', 'every' => 60]], '"steam-api.rate_limit.limits.0.allow" cannot be "".'],
    'every left out' => [[['allow' => 1]], '"steam-api.rate_limit.limits.0.every" cannot be null.'],
    'every zero' => [[['allow' => 1, 'every' => 0]], '"steam-api.rate_limit.limits.0.every" cannot be 0.'],
    'every another word' => [[['allow' => 1, 'every' => 'week']], '"steam-api.rate_limit.limits.0.every" cannot be "week".'],
    'every a capitalised day' => [[['allow' => 1, 'every' => 'Day']], '"steam-api.rate_limit.limits.0.every" cannot be "Day".'],
    'every a boolean' => [[['allow' => 1, 'every' => true]], '"steam-api.rate_limit.limits.0.every" cannot be true.'],
    'sleep not a boolean' => [[['allow' => 1, 'every' => 60, 'sleep' => 'maybe']], '"steam-api.rate_limit.limits.0.sleep" cannot be "maybe".'],
    'a keyed entry' => [['daily' => ['allow' => 0, 'every' => 'day']], '"steam-api.rate_limit.limits.daily.allow" cannot be 0.'],
    'the second entry' => [[['allow' => 1, 'every' => 60], ['allow' => 1, 'every' => -1]], '"steam-api.rate_limit.limits.1.every" cannot be -1.'],
    'a repeated window' => [
        [['allow' => 5, 'every' => 60], ['allow' => 1, 'every' => 1], ['allow' => '5', 'every' => '60', 'sleep' => true]],
        '"steam-api.rate_limit.limits.0" and "steam-api.rate_limit.limits.2" share',
    ],
]);

it('keeps a rolling day apart from the one reset at 00:00 UTC', function (): void {
    config()->set('steam-api.rate_limit.limits', [
        ['allow' => 5, 'every' => 86_400],
        ['allow' => 5, 'every' => 'day'],
    ]);

    expect(rateLimitOptions()->limits())->toHaveCount(2);
});

it('reads the cool-down', function (mixed $cooldown, int $seconds): void {
    config()->set('steam-api.rate_limit.cooldown', $cooldown);

    expect(rateLimitOptions()->cooldown())->toBe($seconds);
})->with([
    'unset' => [null, 60],
    'blank' => ['  ', 60],
    'a string from env()' => ['120', 120],
    'a second' => [1, 1],
]);

it('rejects a cool-down that is not a whole number of seconds', function (mixed $cooldown): void {
    config()->set('steam-api.rate_limit.cooldown', $cooldown);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamRateLimitOptionException::class, '"steam-api.rate_limit.cooldown"');
})->with([
    'zero' => '0',
    'negative' => '-5',
    'a word' => 'abc',
    'a boolean' => true,
    'a fraction' => 1.5,
]);

it('refuses requests for the configured cool-down after a 429 without Retry-After', function (): void {
    config()->set('steam-api.rate_limit.cooldown', '300');

    Steam::fake([GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 429)]);

    $limit = refusedLimit(fn (): int => Steam::stats()->currentPlayers(440));

    expect($limit->getRemainingSeconds())->toBeGreaterThanOrEqual(299)->toBeLessThanOrEqual(300)
        ->and(fn (): int => Steam::stats()->currentPlayers(440))->toThrow(SteamRateLimitException::class);

    Steam::assertSentCount(1);
});

it('names the config key, the env var and the fix', function (Closure $exception, string $message): void {
    expect($exception())->getMessage()->toBe($message);
})->with([
    'store' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::store('redis-x'),
        'The configured Steam rate limit option "steam-api.rate_limit.store" cannot be "redis-x". Set '
        .'STEAM_API_RATE_LIMIT_STORE in your .env file, or that config value, to a store name from "cache.stores", '
        .'or leave it unset for the default store.',
    ],
    'cool-down' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::cooldown('0'),
        'The configured Steam rate limit option "steam-api.rate_limit.cooldown" cannot be "0". Set '
        .'STEAM_API_RATE_LIMIT_COOLDOWN in your .env file, or that config value, to the seconds a 429 without '
        .'Retry-After holds requests back, at least 1.',
    ],
    'limits' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::limits('ten'),
        'The configured Steam rate limit option "steam-api.rate_limit.limits" cannot be "ten". Set that config value '
        ."to a list of limits, [] to meter nothing locally, or null for Steam's daily budget.",
    ],
    'entry' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::entry(0, 5),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0" cannot be 5. Set that config value '
        .'to an array of "allow", "every" and an optional "sleep".',
    ],
    'unknown key' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::unknownKey(0, 'evry', 60),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0.evry" cannot be 60. Remove it, '
        .'since a limit takes only "allow", "every" and "sleep".',
    ],
    'allow' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::allow('daily', 0),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.daily.allow" cannot be 0. Set that '
        .'config value to the requests the window allows, at least 1.',
    ],
    'every' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::every(0, 'week'),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0.every" cannot be "week". Set that '
        .'config value to the window in seconds, at least 1, or "day" for one that resets at 00:00 UTC.',
    ],
    'sleep' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::sleep(0, 'maybe'),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0.sleep" cannot be "maybe". Set that '
        .'config value to true to wait the window out, or false to throw.',
    ],
    'duplicate' => [
        fn (): InvalidSteamRateLimitOptionException => InvalidSteamRateLimitOptionException::duplicate(2, 0),
        'The configured Steam rate limits "steam-api.rate_limit.limits.0" and "steam-api.rate_limit.limits.2" share '
        .'"allow" and "every", so they would count as one. Remove one, or change either.',
    ],
]);
