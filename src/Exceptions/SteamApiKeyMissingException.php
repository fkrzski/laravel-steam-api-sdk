<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when the Steam Web API key is missing from the configuration.
 *
 * Raised lazily, the first time the connector is resolved, so an application can
 * still boot and publish the config before a key is set.
 */
final class SteamApiKeyMissingException extends SteamApiException
{
    public function __construct()
    {
        parent::__construct(
            'The Steam Web API key is not configured. Set STEAM_API_KEY in your .env file, '
            .'or the "steam-api.key" config value. Get a key from https://steamcommunity.com/dev.'
        );
    }
}
