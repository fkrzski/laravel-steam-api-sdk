<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Logging;

use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestFailed;
use Fkrzski\LaravelSteamApiSdk\Events\SteamResponseReceived;
use Fkrzski\LaravelSteamApiSdk\SteamSettings;
use Illuminate\Log\LogManager;

/**
 * Writes Steam traffic to the configured channel.
 *
 * A failure goes out at warning rather than error: the application may well
 * catch it, and one it does not still reaches the exception handler.
 *
 * @internal
 */
final readonly class SteamTrafficLogger
{
    public function __construct(
        private SteamSettings $settings,
        private LogManager $log,
    ) {}

    public function logFailure(SteamRequestFailed $event): void
    {
        $channel = $this->settings->logChannel();

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
        $channel = $this->settings->logChannel();

        if ($channel === null || ! $this->settings->logResponses()) {
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
