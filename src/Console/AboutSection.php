<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Console;

use Closure;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLanguageException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\SteamApiKeyMissingException;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use JsonException;
use Saloon\RateLimitPlugin\Exceptions\LimitException;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;

/**
 * The "Steam API" section of `php artisan about`.
 *
 * Every value is a closure, and the section itself is registered by class name,
 * so nothing here runs until the command renders. Resolving the connector and
 * reading the rate-limit counter out of the cache must never happen on boot.
 */
final readonly class AboutSection
{
    /**
     * Number of trailing API key characters left visible.
     */
    private const int VISIBLE_KEY_CHARACTERS = 4;

    /**
     * Width of the mask standing in for the hidden part of the API key. Keys
     * this short — or shorter — are masked in full.
     */
    private const int MASK_LENGTH = 8;

    private const string MASK_CHARACTER = '*';

    private const string MISSING = 'NOT SET';

    private const string UNKNOWN = 'UNKNOWN';

    private const string DISABLED = 'disabled';

    private const string INVALID = 'INVALID';

    public function __construct(
        private Container $container,
        private ConfigRepository $config,
    ) {}

    /**
     * @return array<string, Closure(): string>
     */
    public function __invoke(): array
    {
        return [
            'API Key' => $this->maskedApiKey(...),
            'Rate Limit Store' => $this->rateLimitStore(...),
            'Daily Requests Remaining' => $this->remainingDailyRequests(...),
            'Route Binding' => $this->routeBinding(...),
            'Language' => $this->language(...),
        ];
    }

    /**
     * Whether the binding claims a route parameter, and which one.
     *
     * Repeats the provider's strict check so the row cannot report a state the
     * provider does not act on.
     */
    private function routeBinding(): string
    {
        if ($this->config->get('steam-api.route_binding.enabled') !== true) {
            return self::DISABLED;
        }

        $parameter = $this->config->get('steam-api.route_binding.parameter');

        return sprintf('enabled (%s)', is_string($parameter) ? $parameter : self::UNKNOWN);
    }

    /**
     * The default language every localised request carries, if one is set.
     *
     * Repeats the provider's rule so the row cannot report a state the provider
     * does not act on: a blank value is no language, and a code Steam does not
     * know is a misconfiguration the connector refuses to be built with.
     */
    private function language(): string
    {
        $language = $this->config->get('steam-api.language');
        $code = is_string($language) ? trim($language) : '';

        if ($code === '') {
            return self::MISSING;
        }

        return Language::tryFrom($code) instanceof Language ? $code : self::INVALID;
    }

    /**
     * The configured API key with everything but its last few characters hidden.
     *
     * The full key is never rendered: `about` output is routinely pasted into
     * bug reports, and the key is a credential.
     */
    private function maskedApiKey(): string
    {
        $key = $this->config->get('steam-api.key');

        if (! is_string($key) || trim($key) === '') {
            return self::MISSING;
        }

        $key = trim($key);
        $mask = str_repeat(self::MASK_CHARACTER, self::MASK_LENGTH);

        if (mb_strlen($key) <= self::MASK_LENGTH) {
            return $mask;
        }

        return $mask.mb_substr($key, -self::VISIBLE_KEY_CHARACTERS);
    }

    /**
     * The cache store the rate-limit counter is kept in.
     *
     * The provider hands the plugin a {@see LaravelCacheStore} over the default
     * store, so the daily budget lives wherever `cache.default` points.
     */
    private function rateLimitStore(): string
    {
        $store = $this->config->get('cache.default');

        return is_string($store) ? $store : self::UNKNOWN;
    }

    /**
     * How much of the daily request budget is left.
     */
    private function remainingDailyRequests(): string
    {
        $limit = $this->dailyLimit();

        if (! $limit instanceof Limit) {
            return self::UNKNOWN;
        }

        return sprintf(
            '%s of %s',
            number_format($limit->getAllow() - $limit->getHits()),
            number_format($limit->getAllow()),
        );
    }

    /**
     * The connector's daily limit, hydrated from the rate-limit store.
     *
     * `about` is a diagnostics command, so the states it is expected to run into
     * — an app the connector cannot be built for yet, and counter data the plugin
     * cannot read back — degrade to "unknown" instead of taking the whole command
     * down. The rows above name the misconfiguration this one cannot report.
     */
    private function dailyLimit(): ?Limit
    {
        try {
            $connector = $this->container->make(SteamConnector::class);

            // The 429 detector is response-driven and carries no budget of its
            // own — the daily quota is the limit that counts hits upfront.
            $limit = array_find(
                $connector->getLimits(),
                static fn (Limit $limit): bool => ! $limit->usesResponse(),
            );

            return $limit?->update($connector->rateLimitStore());
        } catch (SteamApiKeyMissingException|InvalidSteamLanguageException|LimitException|JsonException) {
            return null;
        }
    }
}
