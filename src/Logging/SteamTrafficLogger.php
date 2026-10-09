<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Logging;

use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestFailed;
use Fkrzski\LaravelSteamApiSdk\Events\SteamResponseReceived;
use Illuminate\Log\LogManager;

/**
 * Writes Steam traffic to the configured channel.
 *
 * A failure goes out at warning rather than error: the application may well
 * catch it, and one it does not still reaches the exception handler.
 */
final readonly class SteamTrafficLogger
{
    public function __construct(
        private LoggingOptions $options,
        private LogManager $log,
    ) {}

    public function logFailure(SteamRequestFailed $event): void
    {
        $channel = $this->options->channel();

        if ($channel === null) {
            return;
        }

        $this->log->channel($channel)->warning($event->exception->getMessage(), [
            'exception' => $event->exception::class,
            'code' => $event->exception->getCode(),
        ]);
    }

    public function logResponse(SteamResponseReceived $event): void
    {
        $channel = $this->options->channel();

        if ($channel === null || ! $this->options->responses()) {
            return;
        }

        $this->log->channel($channel)->debug(sprintf(
            '%s: HTTP %d in %.0fms, attempt %d',
            $event->method,
            $event->status,
            $event->duration * 1000,
            $event->attempt,
        ), ['query' => $event->query]);
    }
}
