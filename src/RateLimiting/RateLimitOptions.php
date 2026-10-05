<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\RateLimiting;

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamRateLimitStoreException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * The rate limit options, read out of the `steam-api.rate_limit` block.
 *
 * The provider builds the plugin's store from these and `about` reports them,
 * so the two cannot disagree.
 */
final readonly class RateLimitOptions
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    /**
     * The cache store the counter is kept in, null for the default store.
     *
     * Checked against `cache.stores` before the cache manager sees the name, so
     * a typo is reported against this key rather than as a missing store.
     *
     * @throws InvalidSteamRateLimitStoreException when the cache config defines no such store
     */
    public function store(): ?string
    {
        $store = $this->config->get('steam-api.rate_limit.store');
        $name = is_string($store) ? trim($store) : $store;

        if ($name === null || $name === '') {
            return null;
        }

        return is_string($name) && is_array($this->config->get('cache.stores.'.$name))
            ? $name
            : throw new InvalidSteamRateLimitStoreException($store);
    }
}
