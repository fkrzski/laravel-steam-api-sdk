<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamRateLimitStoreException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\RateLimitOptions;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Support\Facades\Cache;
use Saloon\RateLimitPlugin\Limit;

mutates(RateLimitOptions::class, InvalidSteamRateLimitStoreException::class);

function rateLimitOptions(): RateLimitOptions
{
    return app(RateLimitOptions::class);
}

/**
 * Send one faked request, so the plugin saves the daily counter, and return
 * the key it saved it under.
 */
function sendCountedRequest(): string
{
    fakeSteamEndpoints();

    Steam::resolveVanityUrl('gabelogannewell');

    $limit = array_find(Steam::connector()->getLimits(), static fn (Limit $limit): bool => ! $limit->usesResponse());

    return $limit?->getName() ?? throw new LogicException('The connector declares no daily limit.');
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

it('falls back to the default store when the published config drops the option', function (): void {
    config()->set('steam-api.rate_limit', []);

    expect(rateLimitOptions()->store())->toBeNull();
});

it('throws when the cache config defines no such store', function (mixed $store): void {
    config()->set('steam-api.rate_limit.store', $store);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamRateLimitStoreException::class);
})->with([
    'an undefined name' => 'redis-x',
    'the null store' => 'null',
    'a number' => 123,
    'a boolean' => true,
    'an array' => [[]],
]);

it('names the config key, the env var and the rejected store', function (): void {
    expect(new InvalidSteamRateLimitStoreException('redis-x'))->getMessage()->toBe(
        'The configured Steam rate limit store "redis-x" is not one config/cache.php defines. Set '
        .'STEAM_API_RATE_LIMIT_STORE in your .env file, or the "steam-api.rate_limit.store" config value, '
        .'to a store name from "cache.stores", or leave it unset for the default store.',
    );
});
