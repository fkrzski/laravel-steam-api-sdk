<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Fkrzski\LaravelSteamApiSdk\Contracts\SteamLanguageResolver;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamConfigException;
use Fkrzski\LaravelSteamApiSdk\Monitoring\NightwatchReporting;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\UtcDayLimit;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use ReflectionClass;
use Saloon\Contracts\Sender;
use Saloon\RateLimitPlugin\Limit;

/**
 * The `steam-api` config block, read and checked in one place.
 *
 * `env()` hands back strings, so every value is cast and checked here rather
 * than left to {@see SteamConfig}, whose exceptions name its own properties
 * instead of the config key an application sets. The provider builds the
 * connector from these, the listeners log through them and `about` reports
 * them, so none of them can disagree.
 *
 * @internal
 */
final readonly class SteamSettings
{
    public const int DEFAULT_COOLDOWN = 60;

    private const string DEFAULT_ROUTE_PARAMETER = 'steamId';

    private const array LIMIT_KEYS = ['allow' => true, 'every' => true, 'sleep' => true];

    public function __construct(
        private ConfigRepository $config,
        private Application $app,
        private NightwatchReporting $nightwatch,
    ) {}

    /**
     * The configured Steam Web API key, null when none is set.
     *
     * A key that is unset, blank or not a string is not an error here: the
     * connector still has to be built for the endpoints Steam serves
     * anonymously, and it refuses the requests that do need one itself.
     * Null rather than blank, because {@see SteamConfig} rejects a blank key.
     */
    public function apiKey(): ?string
    {
        $key = $this->value('key');

        return is_string($key) ? $key : null;
    }

    /**
     * The default language, sent on every request that localises its payload.
     *
     * A configured code wins, an unset value falls to the locale, and one blanked
     * out on purpose sends no language at all. The locale is read when this is
     * called, and the scoped connector calls it once, so a later `setLocale()`
     * does not reach it — switching mid-request means passing the language to the call.
     *
     * @throws InvalidSteamConfigException when the value is not one of Steam's codes
     */
    public function language(): ?Language
    {
        if ($this->languageFollowsLocale()) {
            $resolver = $this->app->make(SteamLanguageResolver::class);

            return $resolver($this->app->getLocale());
        }

        $code = $this->value('language');

        if (! is_string($code)) {
            return null;
        }

        return Language::tryFrom($code) ?? throw InvalidSteamConfigException::language($code);
    }

    /**
     * Whether the language is left to the locale rather than set, or blanked out, in config.
     */
    public function languageFollowsLocale(): bool
    {
        return $this->config->get('steam-api.language') === null;
    }

    /**
     * The route parameter resolved to a {@see SteamId}, null while the binding is off.
     *
     * The name is claimed application-wide, so only a literal `true` opts in.
     */
    public function routeParameter(): ?string
    {
        if ($this->config->get('steam-api.route_binding.enabled') !== true) {
            return null;
        }

        $parameter = $this->value('route_binding.parameter');

        return is_string($parameter) ? $parameter : self::DEFAULT_ROUTE_PARAMETER;
    }

    /**
     * Whether a Steam failure renders as the status it maps to. Only a literal
     * `false` turns it off.
     */
    public function rendersExceptions(): bool
    {
        return $this->config->get('steam-api.exceptions.render') !== false;
    }

    /**
     * Null leaves Saloon's own default in place.
     */
    public function connectTimeout(): ?float
    {
        return $this->seconds('http.connect_timeout');
    }

    /**
     * Null leaves Saloon's own default in place.
     */
    public function requestTimeout(): ?float
    {
        return $this->seconds('http.request_timeout');
    }

    public function tries(): int
    {
        return $this->count('http.retry.tries', min: 1, default: 1);
    }

    public function retryInterval(): int
    {
        return $this->count('http.retry.interval', min: 0, default: 0);
    }

    public function exponentialBackoff(): bool
    {
        return $this->flag('http.retry.exponential_backoff');
    }

    /**
     * Null leaves the base SDK's GuzzleSender in place.
     *
     * @return class-string<Sender>|null
     */
    public function sender(): ?string
    {
        $class = $this->value('http.sender');

        if ($class === null) {
            return null;
        }

        if (! is_string($class) || ! is_subclass_of($class, Sender::class)) {
            throw InvalidSteamConfigException::option('http.sender', $class);
        }

        // The container keys bindings by the declared name, which "\App\…" or another casing would miss.
        return new ReflectionClass($class)->getName();
    }

    /**
     * Whether the connector sends through Nightwatch's GuzzleSender: a sender
     * left unset reports to Nightwatch while that is enabled.
     */
    public function sendsThroughNightwatch(): bool
    {
        return $this->sender() === null && $this->nightwatch->enabled();
    }

    /**
     * The cache store the counter is kept in, null for the default store.
     *
     * Checked against `cache.stores` before the cache manager sees the name, so
     * a typo is reported against this key rather than as a missing store.
     */
    public function rateLimitStore(): ?string
    {
        return $this->name('rate_limit.store', 'cache.stores');
    }

    /**
     * The limits replacing the base SDK's daily budget, null to keep it.
     *
     * Configured as plain arrays, because `config:cache` writes the config out
     * with `var_export()` and a {@see Limit} does not survive that.
     *
     * @return array<Limit>|null
     */
    public function rateLimits(): ?array
    {
        $entries = $this->config->get('steam-api.rate_limit.limits');

        if ($entries === null) {
            return null;
        }

        if (! is_array($entries)) {
            throw InvalidSteamConfigException::option('rate_limit.limits', $entries);
        }

        $limits = [];
        $keys = [];

        foreach ($entries as $key => $entry) {
            $limit = $this->limit($key, $entry);

            // The name is built from `allow` and `every`, and the plugin keeps one counter per name.
            $earlier = $keys[$limit->getName()] ?? null;

            if ($earlier !== null) {
                throw InvalidSteamConfigException::duplicateLimits($key, $earlier);
            }

            $keys[$limit->getName()] = $key;
            $limits[$key] = $limit;
        }

        return $limits;
    }

    /**
     * The seconds a 429 without `Retry-After` holds requests back.
     */
    public function rateLimitCooldown(): int
    {
        return $this->count('rate_limit.cooldown', min: 1, default: self::DEFAULT_COOLDOWN);
    }

    /**
     * The channel Steam traffic is logged to, null to log nothing.
     *
     * Checked against `logging.channels` before the log manager sees the name,
     * so a typo is reported against this key rather than swallowed.
     */
    public function logChannel(): ?string
    {
        return $this->name('logging.channel', 'logging.channels');
    }

    /**
     * Whether every response is logged too, not just the failures.
     */
    public function logResponses(): bool
    {
        return $this->flag('logging.responses');
    }

    /**
     * Read both logging options, so a rejected one surfaces when the connector is built.
     *
     * The listeners read them per event, and the base SDK drops a throw from a
     * failure listener, so a misnamed channel would otherwise never be reported.
     */
    public function validateLogging(): void
    {
        $this->logChannel();
        $this->logResponses();
    }

    private function limit(int|string $key, mixed $entry): Limit
    {
        if (! is_array($entry)) {
            throw InvalidSteamConfigException::limit($key, null, $entry);
        }

        $unknown = array_key_first(array_diff_key($entry, self::LIMIT_KEYS));

        if ($unknown !== null) {
            throw InvalidSteamConfigException::limit($key, $unknown, $entry[$unknown]);
        }

        $allow = $entry['allow'] ?? null;
        $requests = $this->wholeNumber($allow, 1) ?? throw InvalidSteamConfigException::limit($key, 'allow', $allow);

        $every = $entry['every'] ?? null;

        $limit = $every === 'day'
            ? UtcDayLimit::requests($requests)
            : Limit::allow($requests)->everySeconds(
                $this->wholeNumber($every, 1) ?? throw InvalidSteamConfigException::limit($key, 'every', $every),
            );

        $sleep = $entry['sleep'] ?? null;
        $sleeps = $this->boolean($sleep) ?? throw InvalidSteamConfigException::limit($key, 'sleep', $sleep);

        return $sleeps ? $limit->sleep() : $limit;
    }

    private function seconds(string $key): ?float
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        // filter_var() alone would read `true` as 1.
        $seconds = is_numeric($value)
            ? filter_var($value, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]])
            : false;

        // Guzzle counts whole milliseconds in an int, so the base SDK refuses what rounds to none or overflows it.
        if ($seconds === false || ($seconds > 0 && $seconds < 0.001) || $seconds * 1000 >= PHP_INT_MAX) {
            throw InvalidSteamConfigException::option($key, $value);
        }

        return $seconds;
    }

    private function count(string $key, int $min, int $default): int
    {
        $value = $this->value($key);

        if ($value === null) {
            return $default;
        }

        return $this->wholeNumber($value, $min) ?? throw InvalidSteamConfigException::option($key, $value);
    }

    private function flag(string $key): bool
    {
        $value = $this->value($key);

        return $this->boolean($value) ?? throw InvalidSteamConfigException::option($key, $value);
    }

    /**
     * A name the `$defined` config block holds an entry for, null while unset.
     */
    private function name(string $key, string $defined): ?string
    {
        $name = $this->value($key);

        if ($name === null) {
            return null;
        }

        return is_string($name) && is_array($this->config->get($defined.'.'.$name))
            ? $name
            : throw InvalidSteamConfigException::option($key, $name);
    }

    private function wholeNumber(mixed $value, int $min): ?int
    {
        $number = is_numeric($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]])
            : false;

        return $number === false ? null : $number;
    }

    /**
     * filter_var() reads an unset value as false, which is the default.
     */
    private function boolean(mixed $value): ?bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * The trimmed value, or null for one left unset: dropped from a config
     * published before the option existed, or blank in the `.env` file.
     */
    private function value(string $key): mixed
    {
        $value = $this->config->get('steam-api.'.$key);

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
