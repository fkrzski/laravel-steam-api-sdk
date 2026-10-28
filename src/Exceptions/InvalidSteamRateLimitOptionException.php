<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when a configured rate limit option is not a value the connector takes.
 *
 * Raised lazily alongside the language, the first time the connector is
 * resolved, and in place of the base SDK's `InvalidRateLimitException` and of
 * Laravel's own error for a missing store — those name `SteamConfig`
 * properties and cache stores rather than the config key an application sets.
 */
final class InvalidSteamRateLimitOptionException extends SteamApiException
{
    /** @internal */
    public static function store(mixed $store): self
    {
        return self::option('store', $store, 'Set STEAM_API_RATE_LIMIT_STORE in your .env file, or that config value, '
            .'to a store name from "cache.stores", or leave it unset for the default store.');
    }

    /** @internal */
    public static function cooldown(mixed $seconds): self
    {
        return self::option('cooldown', $seconds, 'Set STEAM_API_RATE_LIMIT_COOLDOWN in your .env file, or that config '
            .'value, to the seconds a 429 without Retry-After holds requests back, at least 1.');
    }

    /** @internal */
    public static function limits(mixed $limits): self
    {
        return self::option('limits', $limits, 'Set that config value to a list of limits, [] to meter nothing '
            ."locally, or null for Steam's daily budget.");
    }

    /** @internal */
    public static function entry(int|string $key, mixed $entry): self
    {
        return self::option('limits.'.$key, $entry, 'Set that config value to an array of "allow", "every" and an '
            .'optional "sleep".');
    }

    /** @internal */
    public static function unknownKey(int|string $key, int|string $name, mixed $value): self
    {
        return self::option(sprintf('limits.%s.%s', $key, $name), $value, 'Remove it, since a limit takes only '
            .'"allow", "every" and "sleep".');
    }

    /** @internal */
    public static function allow(int|string $key, mixed $allow): self
    {
        return self::option(sprintf('limits.%s.allow', $key), $allow, 'Set that config value to the requests the '
            .'window allows, at least 1.');
    }

    /** @internal */
    public static function every(int|string $key, mixed $every): self
    {
        return self::option(sprintf('limits.%s.every', $key), $every, 'Set that config value to the window in '
            .'seconds, at least 1, or "day" for one that resets at 00:00 UTC.');
    }

    /** @internal */
    public static function sleep(int|string $key, mixed $sleep): self
    {
        return self::option(sprintf('limits.%s.sleep', $key), $sleep, 'Set that config value to true to wait the '
            .'window out, or false to throw.');
    }

    /** @internal */
    public static function duplicate(int|string $key, int|string $earlier): self
    {
        return new self(sprintf(
            'The configured Steam rate limits "steam-api.rate_limit.limits.%s" and "steam-api.rate_limit.limits.%s" '
            .'share "allow" and "every", so they would count as one. Remove one, or change either.',
            $earlier,
            $key,
        ));
    }

    private static function option(string $option, mixed $value, string $fix): self
    {
        return new self(sprintf(
            'The configured Steam rate limit option "steam-api.rate_limit.%s" cannot be %s. %s',
            $option,
            json_encode($value),
            $fix,
        ));
    }
}
