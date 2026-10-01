<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Http;

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamHttpOptionException;
use Fkrzski\SteamApiSdk\SteamConfig;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * The connector's timeouts and retries, read out of the `steam-api.http` block.
 *
 * `env()` hands back strings, so every value is cast and checked here rather
 * than left to {@see SteamConfig}, whose exceptions name its own properties
 * instead of the config key an application sets. The provider builds the
 * connector from these and `about` reports them, so the two cannot disagree.
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
            ?? throw new InvalidSteamHttpOptionException('retry.exponential_backoff', $value);
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

        return $seconds === false ? throw new InvalidSteamHttpOptionException($option, $value) : $seconds;
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

        return $number === false ? throw new InvalidSteamHttpOptionException($option, $value) : $number;
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
