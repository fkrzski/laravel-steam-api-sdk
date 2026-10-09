<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when a configured logging option is not a value the listeners take.
 *
 * Raised lazily alongside the language, the first time the connector is
 * resolved. Left alone, Laravel's log manager would fall back to its emergency
 * logger for a channel it cannot build, writing to `laravel.log` instead.
 */
final class InvalidSteamLoggingOptionException extends SteamApiException
{
    public function __construct(string $option, mixed $value)
    {
        [$env, $expected] = match ($option) {
            'channel' => [
                'STEAM_API_LOG_CHANNEL',
                'a channel name from "logging.channels", or leave it unset to log nothing',
            ],
            default => ['STEAM_API_LOG_RESPONSES', 'true or false'],
        };

        parent::__construct(sprintf(
            'The configured Steam logging option "steam-api.logging.%s" cannot be %s. Set %s in your .env file, '
            .'or that config value, to %s.',
            $option,
            json_encode($value),
            $env,
            $expected,
        ));
    }
}
