<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Events;

use Throwable;

/**
 * Dispatched once per failed request, with the exception the caller gets.
 *
 * An exception carrying Steam's response does not serialize, so a listener for this event stays sync.
 */
final readonly class SteamRequestFailed
{
    public function __construct(
        public Throwable $exception,
    ) {}
}
