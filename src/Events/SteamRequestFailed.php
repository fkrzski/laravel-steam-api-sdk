<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Events;

use Throwable;

/**
 * Dispatched once per failed call, with the exception the caller gets.
 *
 * The exception does not serialize, so a listener for this event cannot be queued.
 */
final readonly class SteamRequestFailed
{
    public function __construct(
        public Throwable $exception,
    ) {}
}
