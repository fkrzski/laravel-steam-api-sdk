<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\RateLimiting;

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamRateLimitOptionException;
use Fkrzski\SteamApiSdk\SteamConfig;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Saloon\RateLimitPlugin\Limit;

/**
 * The rate limit options, read out of the `steam-api.rate_limit` block.
 *
 * `env()` hands back strings, so every value is cast and checked here rather
 * than left to {@see SteamConfig}. The provider builds the connector from these
 * and `about` reports them, so the two cannot disagree.
 *
 * @internal
 */
final readonly class RateLimitOptions
{
    public const int DEFAULT_COOLDOWN = 60;

    private const array ENTRY_KEYS = ['allow' => true, 'every' => true, 'sleep' => true];

    public function __construct(
        private ConfigRepository $config,
    ) {}

    /**
     * The cache store the counter is kept in, null for the default store.
     *
     * Checked against `cache.stores` before the cache manager sees the name, so
     * a typo is reported against this key rather than as a missing store.
     *
     * @throws InvalidSteamRateLimitOptionException when the cache config defines no such store
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
            : throw InvalidSteamRateLimitOptionException::store($store);
    }

    /**
     * The limits replacing the base SDK's daily budget, null to keep it.
     *
     * Configured as plain arrays, because `config:cache` writes the config out
     * with `var_export()` and a {@see Limit} does not survive that.
     *
     * @return array<Limit>|null
     */
    public function limits(): ?array
    {
        $entries = $this->config->get('steam-api.rate_limit.limits');

        if ($entries === null) {
            return null;
        }

        if (! is_array($entries)) {
            throw InvalidSteamRateLimitOptionException::limits($entries);
        }

        $limits = [];
        $keys = [];

        foreach ($entries as $key => $entry) {
            $limit = $this->limit($key, $entry);

            // The name is built from `allow` and `every`, and the plugin keeps one counter per name.
            $earlier = $keys[$limit->getName()] ?? null;

            if ($earlier !== null) {
                throw InvalidSteamRateLimitOptionException::duplicate($key, $earlier);
            }

            $keys[$limit->getName()] = $key;
            $limits[$key] = $limit;
        }

        return $limits;
    }

    /**
     * The seconds a 429 without `Retry-After` holds requests back.
     */
    public function cooldown(): int
    {
        $value = $this->config->get('steam-api.rate_limit.cooldown');

        // Unset: blank in the `.env` file, or dropped from a config published before the option existed.
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return self::DEFAULT_COOLDOWN;
        }

        return $this->wholeNumber($value) ?? throw InvalidSteamRateLimitOptionException::cooldown($value);
    }

    private function limit(int|string $key, mixed $entry): Limit
    {
        if (! is_array($entry)) {
            throw InvalidSteamRateLimitOptionException::entry($key, $entry);
        }

        $unknown = array_key_first(array_diff_key($entry, self::ENTRY_KEYS));

        if ($unknown !== null) {
            throw InvalidSteamRateLimitOptionException::unknownKey($key, $unknown, $entry[$unknown]);
        }

        $allow = $entry['allow'] ?? null;
        $requests = $this->wholeNumber($allow) ?? throw InvalidSteamRateLimitOptionException::allow($key, $allow);

        $every = $entry['every'] ?? null;

        $limit = $every === 'day'
            ? UtcDayLimit::requests($requests)
            : Limit::allow($requests)->everySeconds(
                $this->wholeNumber($every) ?? throw InvalidSteamRateLimitOptionException::every($key, $every),
            );

        $sleep = $entry['sleep'] ?? null;

        // filter_var() reads an unset value as false, which is the default.
        $sleeps = filter_var($sleep, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            ?? throw InvalidSteamRateLimitOptionException::sleep($key, $sleep);

        return $sleeps ? $limit->sleep() : $limit;
    }

    private function wholeNumber(mixed $value): ?int
    {
        $number = is_numeric($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        return $number === false ? null : $number;
    }
}
