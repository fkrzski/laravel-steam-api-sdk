<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Console;

use Closure;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamLanguageResolver;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamHttpOptionException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLanguageException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamRateLimitStoreException;
use Fkrzski\LaravelSteamApiSdk\Http\HttpOptions;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use JsonException;
use Saloon\Config as SaloonConfig;
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

    private const string NOT_METERED = 'not metered (no API key)';

    private const string UNLIMITED = 'unlimited';

    private const string NO_RETRIES = 'none';

    public function __construct(
        private Application $app,
        private ConfigRepository $config,
        private HttpOptions $http,
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
            'Timeouts' => $this->timeouts(...),
            'Retries' => $this->retries(...),
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
     * The default language every localised request carries, and which of the two
     * answers it is — a resolved language and a configured one are not the same.
     *
     * Repeats the provider's rule so the row cannot report a state the provider
     * does not act on. `INVALID` names no source because only config can carry one.
     */
    private function language(): string
    {
        $language = $this->config->get('steam-api.language');

        if ($language === null) {
            return $this->localeLanguage();
        }

        $code = is_string($language) ? trim($language) : '';

        if ($code === '') {
            return self::MISSING.' (config)';
        }

        return Language::tryFrom($code) instanceof Language ? $code.' (config)' : self::INVALID;
    }

    /**
     * Resolved through the container rather than built here, so the row reports
     * the table the connector is actually handed.
     */
    private function localeLanguage(): string
    {
        $locale = $this->app->getLocale();
        $resolver = $this->app->make(SteamLanguageResolver::class);
        $language = $resolver($locale);

        return sprintf(
            '%s (locale: %s)',
            $language instanceof Language ? $language->value : self::MISSING,
            $locale,
        );
    }

    /**
     * How long the connector waits to connect, then for the whole request.
     *
     * Read through the {@see HttpOptions} the provider builds the connector from,
     * so the row cannot report a timeout the connector is not given. An unset one
     * falls back to Saloon's default, as it does on the connector.
     */
    private function timeouts(): string
    {
        try {
            $connect = $this->http->connectTimeout();
            $request = $this->http->requestTimeout();
        } catch (InvalidSteamHttpOptionException) {
            return self::INVALID;
        }

        $timeouts = sprintf(
            'connect %s, request %s',
            $this->seconds($connect ?? SaloonConfig::$defaultConnectionTimeout),
            $this->seconds($request ?? SaloonConfig::$defaultRequestTimeout),
        );

        return $connect === null && $request === null ? $timeouts.' (default)' : $timeouts;
    }

    private function seconds(float|int $seconds): string
    {
        return $seconds > 0 ? $seconds.'s' : self::UNLIMITED;
    }

    /**
     * How many attempts a request gets, and the pause between them.
     *
     * `(default)` only while all three options are what the shipped config sets:
     * a pause configured beside a single attempt changes nothing, but it is set.
     */
    private function retries(): string
    {
        try {
            $tries = $this->http->tries();
            $interval = $this->http->retryInterval();
            $backoff = $this->http->exponentialBackoff();
        } catch (InvalidSteamHttpOptionException) {
            return self::INVALID;
        }

        if ($tries === 1) {
            return $interval === 0 && ! $backoff ? self::NO_RETRIES.' (default)' : self::NO_RETRIES;
        }

        return sprintf('%d attempts, %s', $tries, match (true) {
            $interval === 0 => 'no pause',
            $backoff => sprintf('%dms apart, doubling', $interval),
            default => sprintf('%dms apart', $interval),
        });
    }

    /**
     * The configured API key with everything but its last few characters hidden.
     *
     * The full key is never rendered: `about` output is routinely pasted into
     * bug reports, and the key is a credential.
     */
    private function maskedApiKey(): string
    {
        $key = $this->configuredApiKey();

        if ($key === null) {
            return self::MISSING;
        }

        $mask = str_repeat(self::MASK_CHARACTER, self::MASK_LENGTH);

        if (mb_strlen($key) <= self::MASK_LENGTH) {
            return $mask;
        }

        return $mask.mb_substr($key, -self::VISIBLE_KEY_CHARACTERS);
    }

    /**
     * The configured key, null when none is set.
     *
     * Repeats the provider's rule so neither row can report a key the connector
     * is not built with.
     */
    private function configuredApiKey(): ?string
    {
        $key = $this->config->get('steam-api.key');
        $key = is_string($key) ? trim($key) : '';

        return $key === '' ? null : $key;
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
     *
     * Steam bills the budget to the key, so a connector built without one counts
     * nothing — and is not built just to say so.
     */
    private function remainingDailyRequests(): string
    {
        if ($this->configuredApiKey() === null) {
            return self::NOT_METERED;
        }

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
            $connector = $this->app->make(SteamConnector::class);

            // The 429 detector is response-driven and carries no budget of its
            // own — the daily quota is the limit that counts hits upfront.
            $limit = array_find(
                $connector->getLimits(),
                static fn (Limit $limit): bool => ! $limit->usesResponse(),
            );

            return $limit?->update($connector->rateLimitStore());
        } catch (
            InvalidSteamLanguageException|InvalidSteamHttpOptionException|InvalidSteamRateLimitStoreException
            |LimitException|JsonException
        ) {
            return null;
        }
    }
}
