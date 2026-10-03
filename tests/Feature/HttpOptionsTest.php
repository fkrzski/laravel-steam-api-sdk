<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamHttpOptionException;
use Fkrzski\LaravelSteamApiSdk\Http\HttpOptions;
use Fkrzski\SteamApiSdk\SteamConnector;

mutates(HttpOptions::class, InvalidSteamHttpOptionException::class);

function httpOptions(): HttpOptions
{
    return app(HttpOptions::class);
}

it('builds the connector with saloons timeouts and a single attempt until told otherwise', function (): void {
    $config = app(SteamConnector::class)->steamConfig;

    expect($config->connectTimeout)->toBeNull()
        ->and($config->requestTimeout)->toBeNull()
        ->and($config->tries)->toBe(1)
        ->and($config->retryInterval)->toBe(0)
        ->and($config->exponentialBackoff)->toBeFalse();
});

it('hands the configured timeouts and retries to the connector', function (): void {
    config()->set([
        'steam-api.http.connect_timeout' => '2.5',
        'steam-api.http.request_timeout' => '60',
        'steam-api.http.retry.tries' => '3',
        'steam-api.http.retry.interval' => '500',
        'steam-api.http.retry.exponential_backoff' => true,
    ]);

    $config = app(SteamConnector::class)->steamConfig;

    expect($config->connectTimeout)->toBe(2.5)
        ->and($config->requestTimeout)->toBe(60.0)
        ->and($config->tries)->toBe(3)
        ->and($config->retryInterval)->toBe(500)
        ->and($config->exponentialBackoff)->toBeTrue();
});

it('reads a timeout however it is written', function (mixed $value, float $seconds): void {
    config()->set([
        'steam-api.http.connect_timeout' => $value,
        'steam-api.http.request_timeout' => $value,
    ]);

    expect(httpOptions()->connectTimeout())->toBe($seconds)
        ->and(httpOptions()->requestTimeout())->toBe($seconds);
})->with([
    'a string' => ['2.5', 2.5],
    'a float' => [2.5, 2.5],
    'an integer' => [3, 3.0],
    'surrounded by whitespace' => [' 2.5 ', 2.5],
    'exponent notation' => ['1e3', 1000.0],
    'zero, for no limit' => ['0', 0.0],
]);

it('reads the tries and the interval however they are written', function (mixed $value, int $number): void {
    config()->set([
        'steam-api.http.retry.tries' => $value,
        'steam-api.http.retry.interval' => $value,
    ]);

    expect(httpOptions()->tries())->toBe($number)
        ->and(httpOptions()->retryInterval())->toBe($number);
})->with([
    'a string' => ['3', 3],
    'an integer' => [3, 3],
    'surrounded by whitespace' => [' 3 ', 3],
]);

it('takes a single try and no pause between attempts', function (): void {
    config()->set([
        'steam-api.http.retry.tries' => '1',
        'steam-api.http.retry.interval' => '0',
    ]);

    expect(httpOptions()->tries())->toBe(1)
        ->and(httpOptions()->retryInterval())->toBe(0);
});

it('reads exponential backoff however it is written', function (mixed $value, bool $enabled): void {
    config()->set('steam-api.http.retry.exponential_backoff', $value);

    expect(httpOptions()->exponentialBackoff())->toBe($enabled);
})->with([
    'true' => [true, true],
    'the string true' => ['true', true],
    'one' => ['1', true],
    'the integer one' => [1, true],
    'yes' => ['yes', true],
    'on' => ['on', true],
    'false' => [false, false],
    'the string false' => ['false', false],
    'zero' => ['0', false],
    'the integer zero' => [0, false],
    'no' => ['no', false],
    'off' => ['off', false],
]);

it('falls back to the default for an option left unset', function (mixed $value): void {
    config()->set([
        'steam-api.http.connect_timeout' => $value,
        'steam-api.http.request_timeout' => $value,
        'steam-api.http.retry.tries' => $value,
        'steam-api.http.retry.interval' => $value,
        'steam-api.http.retry.exponential_backoff' => $value,
    ]);

    expect(httpOptions()->connectTimeout())->toBeNull()
        ->and(httpOptions()->requestTimeout())->toBeNull()
        ->and(httpOptions()->tries())->toBe(1)
        ->and(httpOptions()->retryInterval())->toBe(0)
        ->and(httpOptions()->exponentialBackoff())->toBeFalse();
})->with([
    'null' => null,
    'empty string' => '',
    'whitespace only' => '   ',
]);

it('falls back to the defaults when the published config drops the retry block', function (): void {
    config()->set('steam-api.http', ['connect_timeout' => '2.5']);

    expect(httpOptions()->tries())->toBe(1)
        ->and(httpOptions()->retryInterval())->toBe(0)
        ->and(httpOptions()->exponentialBackoff())->toBeFalse();
});

it('throws when a timeout is not a number of seconds', function (string $option, mixed $value): void {
    config()->set('steam-api.http.'.$option, $value);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamHttpOptionException::class);
})->with([
    'connect' => 'connect_timeout',
    'request' => 'request_timeout',
])->with([
    'not a number' => 'abc',
    'negative' => '-1',
    'a negative float' => -0.5,
    'a decimal comma' => '1,5',
    'a boolean' => true,
    'an array' => [[]],
]);

it('throws when the tries are not a whole number of at least one', function (mixed $value): void {
    config()->set('steam-api.http.retry.tries', $value);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamHttpOptionException::class);
})->with([
    'zero' => '0',
    'the integer zero' => 0,
    'negative' => '-1',
    'a fraction' => '2.5',
    'not a number' => 'abc',
    'a boolean' => true,
]);

it('throws when the interval is not a whole number of milliseconds', function (mixed $value): void {
    config()->set('steam-api.http.retry.interval', $value);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamHttpOptionException::class);
})->with([
    'negative' => '-1',
    'a fraction' => '0.5',
    'not a number' => 'abc',
    'a boolean' => true,
]);

it('throws when exponential backoff is not a boolean', function (mixed $value): void {
    config()->set('steam-api.http.retry.exponential_backoff', $value);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamHttpOptionException::class);
})->with([
    'a word' => 'maybe',
    'a number' => 2,
    'an array' => [[]],
]);

it('names the config key, the env var and what to set it to', function (string $option, mixed $value, string $message): void {
    expect(new InvalidSteamHttpOptionException($option, $value))->getMessage()->toBe($message);
})->with([
    'connect timeout' => [
        'connect_timeout',
        'abc',
        'The configured Steam HTTP option "steam-api.http.connect_timeout" cannot be "abc". Set '
        .'STEAM_API_CONNECT_TIMEOUT in your .env file, or that config value, to a number of seconds, 0 for no limit.',
    ],
    'request timeout' => [
        'request_timeout',
        -1,
        'The configured Steam HTTP option "steam-api.http.request_timeout" cannot be -1. Set '
        .'STEAM_API_REQUEST_TIMEOUT in your .env file, or that config value, to a number of seconds, 0 for no limit.',
    ],
    'tries' => [
        'retry.tries',
        '2.5',
        'The configured Steam HTTP option "steam-api.http.retry.tries" cannot be "2.5". Set '
        .'STEAM_API_RETRY_TRIES in your .env file, or that config value, to the total number of attempts, '
        .'1 to never retry.',
    ],
    'interval' => [
        'retry.interval',
        true,
        'The configured Steam HTTP option "steam-api.http.retry.interval" cannot be true. Set '
        .'STEAM_API_RETRY_INTERVAL in your .env file, or that config value, to the milliseconds between '
        .'attempts, 0 for no pause.',
    ],
    'exponential backoff' => [
        'retry.exponential_backoff',
        [],
        'The configured Steam HTTP option "steam-api.http.retry.exponential_backoff" cannot be []. Set '
        .'STEAM_API_RETRY_EXPONENTIAL_BACKOFF in your .env file, or that config value, to true or false.',
    ],
]);
