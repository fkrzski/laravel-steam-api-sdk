<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when a `steam-api` config value is not one the bridge can use.
 *
 * Raised lazily, the first time the connector is resolved, so a rejected value
 * surfaces on the first Steam call rather than taking the application down at
 * boot. It stands in for the base SDK's `InvalidTimeoutException`,
 * `InvalidRetryException` and `InvalidRateLimitException`, and for Laravel's own
 * errors for a missing store or channel — those name `SteamConfig` properties,
 * cache stores and log channels rather than the config key an application sets.
 */
final class InvalidSteamConfigException extends SteamApiException
{
    /**
     * The env var each option is read from, null where only the config file sets it,
     * and what to set it to.
     */
    private const array OPTIONS = [
        'http.connect_timeout' => ['STEAM_API_CONNECT_TIMEOUT', 'a number of seconds from 0.001, 0 for no limit'],
        'http.request_timeout' => ['STEAM_API_REQUEST_TIMEOUT', 'a number of seconds from 0.001, 0 for no limit'],
        'http.retry.tries' => ['STEAM_API_RETRY_TRIES', 'the total number of attempts, 1 to never retry'],
        'http.retry.interval' => ['STEAM_API_RETRY_INTERVAL', 'the milliseconds between attempts, 0 for no pause'],
        'http.retry.exponential_backoff' => ['STEAM_API_RETRY_EXPONENTIAL_BACKOFF', 'true or false'],
        'http.sender' => [
            'STEAM_API_SENDER',
            'the name of a class implementing Saloon\Contracts\Sender, or leave it unset for Guzzle',
        ],
        'rate_limit.store' => [
            'STEAM_API_RATE_LIMIT_STORE',
            'a store name from "cache.stores", or leave it unset for the default store',
        ],
        'rate_limit.cooldown' => [
            'STEAM_API_RATE_LIMIT_COOLDOWN',
            'the seconds a 429 without Retry-After holds requests back, at least 1',
        ],
        'rate_limit.limits' => [null, "a list of limits, [] to meter nothing locally, or null for Steam's daily budget"],
        'logging.channel' => [
            'STEAM_API_LOG_CHANNEL',
            'a channel name from "logging.channels", or leave it unset to log nothing',
        ],
        'logging.responses' => ['STEAM_API_LOG_RESPONSES', 'true or false'],
    ];

    /** @internal */
    public static function language(string $code): self
    {
        return new self(sprintf(
            'The configured Steam language "%s" is not one Steam accepts. Set STEAM_API_LANGUAGE in your .env '
            .'file, or the "steam-api.language" config value, to a code from %s — Valve spells them its own way, '
            .'so "koreana" and "brazilian" rather than the ISO codes.',
            $code,
            Language::class,
        ));
    }

    /** @internal */
    public static function option(string $key, mixed $value): self
    {
        [$env, $expected] = self::OPTIONS[$key];

        return self::invalid($key, $value, $env === null
            ? sprintf('Set that config value to %s.', $expected)
            : sprintf('Set %s in your .env file, or that config value, to %s.', $env, $expected));
    }

    /**
     * A rejected entry of `rate_limit.limits`, or one field of it when `$field` is set.
     *
     * @internal
     */
    public static function limit(int|string $key, int|string|null $field, mixed $value): self
    {
        $option = $field === null ? 'rate_limit.limits.'.$key : sprintf('rate_limit.limits.%s.%s', $key, $field);

        return self::invalid($option, $value, match ($field) {
            null => 'Set that config value to an array of "allow", "every" and an optional "sleep".',
            'allow' => 'Set that config value to the requests the window allows, at least 1.',
            'every' => 'Set that config value to the window in seconds, at least 1, or "day" for one that resets '
                .'at 00:00 UTC.',
            'sleep' => 'Set that config value to true to wait the window out, or false to throw.',
            default => 'Remove it, since a limit takes only "allow", "every" and "sleep".',
        });
    }

    /** @internal */
    public static function duplicateLimits(int|string $key, int|string $earlier): self
    {
        return new self(sprintf(
            'The configured Steam rate limits "steam-api.rate_limit.limits.%s" and "steam-api.rate_limit.limits.%s" '
            .'share "allow" and "every", so they would count as one. Remove one, or change either.',
            $earlier,
            $key,
        ));
    }

    private static function invalid(string $option, mixed $value, string $fix): self
    {
        return new self(sprintf(
            'The configured Steam %s option "steam-api.%s" cannot be %s. %s',
            match (true) {
                str_starts_with($option, 'http.') => 'HTTP',
                str_starts_with($option, 'logging.') => 'logging',
                default => 'rate limit',
            },
            $option,
            // json_encode() would double every backslash in a class name.
            is_string($value) ? '"'.$value.'"' : json_encode($value),
            $fix,
        ));
    }
}
