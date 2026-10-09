<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Logging;

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLoggingOptionException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * The logging options, read out of the `steam-api.logging` block.
 *
 * The listeners log through these and `about` reports them, so the two cannot
 * disagree.
 */
final readonly class LoggingOptions
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    /**
     * Read both options, so a rejected one surfaces when the connector is built.
     *
     * The listeners read them per event, and the base SDK drops a throw from a
     * failure listener, so a misnamed channel would otherwise never be reported.
     */
    public function validate(): void
    {
        $this->channel();
        $this->responses();
    }

    /**
     * The channel Steam traffic is logged to, null to log nothing.
     *
     * Checked against `logging.channels` before the log manager sees the name,
     * so a typo is reported against this key rather than swallowed.
     */
    public function channel(): ?string
    {
        $channel = $this->config->get('steam-api.logging.channel');
        $name = is_string($channel) ? trim($channel) : $channel;

        if ($name === null || $name === '') {
            return null;
        }

        return is_string($name) && is_array($this->config->get('logging.channels.'.$name))
            ? $name
            : throw new InvalidSteamLoggingOptionException('channel', $channel);
    }

    /**
     * Whether every response is logged too, not just the failures.
     */
    public function responses(): bool
    {
        $value = $this->config->get('steam-api.logging.responses');

        // filter_var() reads an unset value as false, which is the default.
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            ?? throw new InvalidSteamLoggingOptionException('responses', $value);
    }
}
