<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Http;

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamConfigException;
use Fkrzski\SteamApiSdk\SteamConfig;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use ReflectionClass;
use Saloon\Contracts\Sender;

/**
 * The connector's timeouts, retries and sender, read out of the `steam-api.http` block.
 *
 * `env()` hands back strings, so every value is cast and checked here rather
 * than left to {@see SteamConfig}, whose exceptions name its own properties
 * instead of the config key an application sets. The provider builds the
 * connector from these and `about` reports them, so the two cannot disagree.
 *
 * @internal
 */
final readonly class HttpOptions
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    /**
     * Null leaves Saloon's own default in place.
     */
    public function connectTimeout(): ?float
    {
        return $this->seconds('connect_timeout');
    }

    /**
     * Null leaves Saloon's own default in place.
     */
    public function requestTimeout(): ?float
    {
        return $this->seconds('request_timeout');
    }

    public function tries(): int
    {
        return $this->wholeNumber('retry.tries', 1) ?? 1;
    }

    public function retryInterval(): int
    {
        return $this->wholeNumber('retry.interval', 0) ?? 0;
    }

    public function exponentialBackoff(): bool
    {
        $value = $this->value('retry.exponential_backoff');

        // filter_var() reads an unset value as false, which is the default.
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            ?? throw InvalidSteamConfigException::option('http.retry.exponential_backoff', $value);
    }

    /**
     * Null leaves the base SDK's GuzzleSender in place.
     *
     * @return class-string<Sender>|null
     */
    public function sender(): ?string
    {
        $value = $this->value('sender');

        if ($value === null) {
            return null;
        }

        $class = is_string($value) ? trim($value) : $value;

        if (! is_string($class) || ! is_subclass_of($class, Sender::class)) {
            throw InvalidSteamConfigException::option('http.sender', $value);
        }

        // The container keys bindings by the declared name, which "\App\…" or another casing would miss.
        return new ReflectionClass($class)->getName();
    }

    private function seconds(string $option): ?float
    {
        $value = $this->value($option);

        if ($value === null) {
            return null;
        }

        // filter_var() alone would read `true` as 1.
        $seconds = is_numeric($value)
            ? filter_var($value, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]])
            : false;

        // Guzzle counts whole milliseconds in an int, so the base SDK refuses what rounds to none or overflows it.
        if ($seconds === false || ($seconds > 0 && $seconds < 0.001) || $seconds * 1000 >= PHP_INT_MAX) {
            throw InvalidSteamConfigException::option('http.'.$option, $value);
        }

        return $seconds;
    }

    private function wholeNumber(string $option, int $min): ?int
    {
        $value = $this->value($option);

        if ($value === null) {
            return null;
        }

        $number = is_numeric($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]])
            : false;

        return $number === false ? throw InvalidSteamConfigException::option('http.'.$option, $value) : $number;
    }

    /**
     * Null for an option left unset: dropped from a config published before
     * the block existed, or blank in the `.env` file.
     */
    private function value(string $option): mixed
    {
        $value = $this->config->get('steam-api.http.'.$option);

        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
