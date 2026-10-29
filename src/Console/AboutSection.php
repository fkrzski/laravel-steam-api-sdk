<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Console;

use Closure;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamConfigException;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\UtcDayLimit;
use Fkrzski\LaravelSteamApiSdk\SteamSettings;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
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
 *
 * @internal
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

    private const string NOT_METERED = 'not metered';

    private const string UNLIMITED = 'unlimited';

    private const string NO_RETRIES = 'none';

    private const string DEFAULT_SENDER = 'Guzzle';

    private const string NIGHTWATCH_SENDER = 'Guzzle + Nightwatch';

    public function __construct(
        private Application $app,
        private ConfigRepository $config,
        private SteamSettings $settings,
    ) {}

    /**
     * @return array<string, Closure(): string>
     */
    public function __invoke(): array
    {
        return [
            'API Key' => $this->maskedApiKey(...),
            'Rate Limit Store' => $this->rateLimitStore(...),
            'Requests Remaining' => $this->remainingRequests(...),
            '429 Cool-down' => $this->cooldown(...),
            'Route Binding' => $this->routeBinding(...),
            'Language' => $this->language(...),
            'Timeouts' => $this->timeouts(...),
            'Retries' => $this->retries(...),
            'Sender' => $this->sender(...),
            'Logging' => $this->logging(...),
        ];
    }

    /**
     * Whether the binding claims a route parameter, and which one.
     */
    private function routeBinding(): string
    {
        $parameter = $this->settings->routeParameter();

        return $parameter === null ? self::DISABLED : sprintf('enabled (%s)', $parameter);
    }

    /**
     * The default language every localised request carries, and which of the two
     * answers it is — a resolved language and a configured one are not the same.
     * `INVALID` names no source because only config can carry one.
     */
    private function language(): string
    {
        try {
            $language = $this->settings->language();
        } catch (InvalidSteamConfigException) {
            return self::INVALID;
        }

        return sprintf(
            '%s (%s)',
            $language->value ?? self::MISSING,
            $this->settings->languageFollowsLocale() ? 'locale: '.$this->app->getLocale() : 'config',
        );
    }

    /**
     * How long the connector waits to connect, then for the whole request.
     *
     * Read through the {@see SteamSettings} the provider builds the connector from,
     * so the row cannot report a timeout the connector is not given. An unset one
     * falls back to Saloon's default, as it does on the connector.
     */
    private function timeouts(): string
    {
        try {
            $connect = $this->settings->connectTimeout();
            $request = $this->settings->requestTimeout();
        } catch (InvalidSteamConfigException) {
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
            $tries = $this->settings->tries();
            $interval = $this->settings->retryInterval();
            $backoff = $this->settings->exponentialBackoff();
        } catch (InvalidSteamConfigException) {
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
     * The sender class the connector is built with, as configured rather than as
     * the container resolves it, so the row never builds one. Left unset, it is
     * Guzzle, reporting to Nightwatch while that is enabled.
     */
    private function sender(): string
    {
        try {
            $sender = $this->settings->sender();
            $nightwatch = $this->settings->sendsThroughNightwatch();
        } catch (InvalidSteamConfigException) {
            return self::INVALID;
        }

        return $sender ?? ($nightwatch ? self::NIGHTWATCH_SENDER : self::DEFAULT_SENDER).' (default)';
    }

    /**
     * The channel Steam traffic is logged to, and whether responses go there too.
     *
     * Read through the {@see SteamSettings} the listeners log through, so the row
     * cannot name a channel the traffic does not reach.
     */
    private function logging(): string
    {
        try {
            $channel = $this->settings->logChannel();
            $responses = $this->settings->logResponses();
        } catch (InvalidSteamConfigException) {
            return self::INVALID;
        }

        if ($channel === null) {
            return self::DISABLED;
        }

        return $responses ? $channel.' (+ responses)' : $channel;
    }

    /**
     * The configured API key with everything but its last few characters hidden.
     *
     * The full key is never rendered: `about` output is routinely pasted into
     * bug reports, and the key is a credential.
     */
    private function maskedApiKey(): string
    {
        $key = $this->settings->apiKey();

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
     * The cache store the rate-limit counter is kept in.
     *
     * Read through the {@see SteamSettings} the provider builds the plugin's
     * {@see LaravelCacheStore} from, so the row cannot name a store the counter
     * is not in. `(default)` while the option is unset and `cache.default` decides.
     */
    private function rateLimitStore(): string
    {
        try {
            $store = $this->settings->rateLimitStore();
        } catch (InvalidSteamConfigException) {
            return self::INVALID;
        }

        if ($store !== null) {
            return $store;
        }

        $default = $this->config->get('cache.default');

        return (is_string($default) ? $default : self::UNKNOWN).' (default)';
    }

    /**
     * How much of each local limit is left, in the order the connector checks them.
     *
     * Steam bills the default budget to the key, so a connector built without one
     * counts nothing — and is not built just to say so. Configured limits count
     * either way.
     */
    private function remainingRequests(): string
    {
        try {
            $configured = $this->settings->rateLimits();
        } catch (InvalidSteamConfigException) {
            return self::INVALID;
        }

        if ($configured === []) {
            return self::NOT_METERED;
        }

        if ($configured === null && $this->settings->apiKey() === null) {
            return self::NOT_METERED.' (no API key)';
        }

        $limits = $this->localLimits();

        if ($limits === null || $limits === []) {
            return self::UNKNOWN;
        }

        return implode(', ', array_map($this->remaining(...), $limits));
    }

    private function remaining(Limit $limit): string
    {
        $remaining = sprintf(
            '%s of %s %s',
            number_format($limit->getAllow() - $limit->getHits()),
            number_format($limit->getAllow()),
            str_ends_with($limit->getName(), UtcDayLimit::WINDOW)
                ? 'per day'
                : sprintf('per %ds', $limit->getReleaseInSeconds()),
        );

        return $limit->getShouldSleep() ? $remaining.' (sleeps)' : $remaining;
    }

    /**
     * The connector's local limits, hydrated from the rate-limit store.
     *
     * `about` is a diagnostics command, so the states it is expected to run into
     * — an app the connector cannot be built for yet, a sender the container cannot
     * build, and counter data the plugin cannot read back — degrade to "unknown"
     * instead of taking the whole command down. The rows above name the
     * misconfiguration this one cannot report.
     *
     * @return array<Limit>|null
     */
    private function localLimits(): ?array
    {
        try {
            $connector = $this->app->make(SteamConnector::class);
            $store = $connector->rateLimitStore();

            // The 429 detector is response-driven and carries no budget of its own.
            $limits = array_filter($connector->getLimits(), static fn (Limit $limit): bool => ! $limit->usesResponse());

            return array_map(static fn (Limit $limit): Limit => $limit->update($store), $limits);
        } catch (
            InvalidSteamConfigException|BindingResolutionException|LimitException|JsonException
        ) {
            return null;
        }
    }

    /**
     * How long a 429 without `Retry-After` holds requests back.
     */
    private function cooldown(): string
    {
        try {
            $seconds = $this->settings->rateLimitCooldown();
        } catch (InvalidSteamConfigException) {
            return self::INVALID;
        }

        return $seconds === SteamSettings::DEFAULT_COOLDOWN ? $seconds.'s (default)' : $seconds.'s';
    }
}
