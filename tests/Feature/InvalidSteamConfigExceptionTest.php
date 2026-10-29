<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamConfigException;

mutates(InvalidSteamConfigException::class);

it('names the config key, the env var and what to set it to', function (Closure $exception, string $message): void {
    expect($exception())->getMessage()->toBe($message);
})->with([
    'language' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::language('klingon'),
        'The configured Steam language "klingon" is not one Steam accepts. Set STEAM_API_LANGUAGE in your .env '
        .'file, or the "steam-api.language" config value, to a code from Fkrzski\SteamApiSdk\Enums\Language — Valve '
        .'spells them its own way, so "koreana" and "brazilian" rather than the ISO codes.',
    ],
    'connect timeout' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('http.connect_timeout', 'abc'),
        'The configured Steam HTTP option "steam-api.http.connect_timeout" cannot be "abc". Set '
        .'STEAM_API_CONNECT_TIMEOUT in your .env file, or that config value, to a number of seconds from 0.001, '
        .'0 for no limit.',
    ],
    'request timeout' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('http.request_timeout', -1),
        'The configured Steam HTTP option "steam-api.http.request_timeout" cannot be -1. Set '
        .'STEAM_API_REQUEST_TIMEOUT in your .env file, or that config value, to a number of seconds from 0.001, '
        .'0 for no limit.',
    ],
    'tries' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('http.retry.tries', '2.5'),
        'The configured Steam HTTP option "steam-api.http.retry.tries" cannot be "2.5". Set '
        .'STEAM_API_RETRY_TRIES in your .env file, or that config value, to the total number of attempts, '
        .'1 to never retry.',
    ],
    'interval' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('http.retry.interval', true),
        'The configured Steam HTTP option "steam-api.http.retry.interval" cannot be true. Set '
        .'STEAM_API_RETRY_INTERVAL in your .env file, or that config value, to the milliseconds between '
        .'attempts, 0 for no pause.',
    ],
    'exponential backoff' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('http.retry.exponential_backoff', []),
        'The configured Steam HTTP option "steam-api.http.retry.exponential_backoff" cannot be []. Set '
        .'STEAM_API_RETRY_EXPONENTIAL_BACKOFF in your .env file, or that config value, to true or false.',
    ],
    'sender' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('http.sender', 'App\Http\MissingSender'),
        'The configured Steam HTTP option "steam-api.http.sender" cannot be "App\Http\MissingSender". Set '
        .'STEAM_API_SENDER in your .env file, or that config value, to the name of a class implementing '
        .'Saloon\Contracts\Sender, or leave it unset for Guzzle.',
    ],
    'store' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('rate_limit.store', 'redis-x'),
        'The configured Steam rate limit option "steam-api.rate_limit.store" cannot be "redis-x". Set '
        .'STEAM_API_RATE_LIMIT_STORE in your .env file, or that config value, to a store name from "cache.stores", '
        .'or leave it unset for the default store.',
    ],
    'cool-down' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('rate_limit.cooldown', '0'),
        'The configured Steam rate limit option "steam-api.rate_limit.cooldown" cannot be "0". Set '
        .'STEAM_API_RATE_LIMIT_COOLDOWN in your .env file, or that config value, to the seconds a 429 without '
        .'Retry-After holds requests back, at least 1.',
    ],
    'limits' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('rate_limit.limits', 'ten'),
        'The configured Steam rate limit option "steam-api.rate_limit.limits" cannot be "ten". Set that config value '
        ."to a list of limits, [] to meter nothing locally, or null for Steam's daily budget.",
    ],
    'entry' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::limit(0, null, 5),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0" cannot be 5. Set that config value '
        .'to an array of "allow", "every" and an optional "sleep".',
    ],
    'unknown key' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::limit(0, 'evry', 60),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0.evry" cannot be 60. Remove it, '
        .'since a limit takes only "allow", "every" and "sleep".',
    ],
    'unknown position' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::limit(0, 1, 60),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0.1" cannot be 60. Remove it, '
        .'since a limit takes only "allow", "every" and "sleep".',
    ],
    'allow' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::limit('daily', 'allow', 0),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.daily.allow" cannot be 0. Set that '
        .'config value to the requests the window allows, at least 1.',
    ],
    'every' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::limit(0, 'every', 'week'),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0.every" cannot be "week". Set that '
        .'config value to the window in seconds, at least 1, or "day" for one that resets at 00:00 UTC.',
    ],
    'sleep' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::limit(0, 'sleep', 'maybe'),
        'The configured Steam rate limit option "steam-api.rate_limit.limits.0.sleep" cannot be "maybe". Set that '
        .'config value to true to wait the window out, or false to throw.',
    ],
    'duplicate' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::duplicateLimits(2, 0),
        'The configured Steam rate limits "steam-api.rate_limit.limits.0" and "steam-api.rate_limit.limits.2" share '
        .'"allow" and "every", so they would count as one. Remove one, or change either.',
    ],
    'channel' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('logging.channel', 'papertrail-x'),
        'The configured Steam logging option "steam-api.logging.channel" cannot be "papertrail-x". Set '
        .'STEAM_API_LOG_CHANNEL in your .env file, or that config value, to a channel name from '
        .'"logging.channels", or leave it unset to log nothing.',
    ],
    'responses' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('logging.responses', 'maybe'),
        'The configured Steam logging option "steam-api.logging.responses" cannot be "maybe". Set '
        .'STEAM_API_LOG_RESPONSES in your .env file, or that config value, to true or false.',
    ],
]);

it('quotes a rejected string as written', function (Closure $exception, string $value): void {
    expect($exception())->getMessage()->toContain(' cannot be '.$value.'. ');
})->with([
    'a slash' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('logging.channel', 'logs/steam'),
        '"logs/steam"',
    ],
    'a backslash' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::option('rate_limit.store', 'redis\1'),
        '"redis\1"',
    ],
    'a non-ascii character' => [
        fn (): InvalidSteamConfigException => InvalidSteamConfigException::limit(0, 'every', 'tydzień'),
        '"tydzień"',
    ],
]);
