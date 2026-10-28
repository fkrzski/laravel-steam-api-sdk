<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when a configured timeout, retry or sender option is not a value the
 * connector takes.
 *
 * Raised lazily alongside the language, the first time the connector is
 * resolved, and in place of the base SDK's `InvalidTimeoutException` and
 * `InvalidRetryException` — those name `SteamConfig` properties, which an
 * application configuring the bridge never sets.
 */
final class InvalidSteamHttpOptionException extends SteamApiException
{
    /** @internal */
    public function __construct(string $option, mixed $value)
    {
        parent::__construct(sprintf(
            'The configured Steam HTTP option "steam-api.http.%s" cannot be %s. Set %s in your .env file, '
            .'or that config value, to %s.',
            $option,
            // json_encode() would double every backslash in a class name.
            is_string($value) ? '"'.$value.'"' : json_encode($value),
            'STEAM_API_'.mb_strtoupper(str_replace('.', '_', $option)),
            match ($option) {
                'retry.tries' => 'the total number of attempts, 1 to never retry',
                'retry.interval' => 'the milliseconds between attempts, 0 for no pause',
                'retry.exponential_backoff' => 'true or false',
                'sender' => 'the name of a class implementing Saloon\Contracts\Sender, or leave it unset for Guzzle',
                default => 'a number of seconds from 0.001, 0 for no limit',
            },
        ));
    }
}
