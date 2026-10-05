<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when the configured rate limit store is not one the cache config
 * defines.
 *
 * Raised lazily alongside the language, the first time the connector is
 * resolved, and in place of Laravel's own `InvalidArgumentException`, which
 * names the store but not the config key an application has to fix.
 */
final class InvalidSteamRateLimitStoreException extends SteamApiException
{
    public function __construct(mixed $store)
    {
        parent::__construct(sprintf(
            'The configured Steam rate limit store %s is not one config/cache.php defines. Set '
            .'STEAM_API_RATE_LIMIT_STORE in your .env file, or the "steam-api.rate_limit.store" config value, '
            .'to a store name from "cache.stores", or leave it unset for the default store.',
            json_encode($store),
        ));
    }
}
