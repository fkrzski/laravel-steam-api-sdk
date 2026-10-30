<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\RateLimiting;

/**
 * One limit's share of the budget, as `about` reports it.
 *
 * @internal
 */
final readonly class Allowance
{
    public function __construct(
        public int $allow,
        public int $left,
        // Null for the window closing at 00:00 UTC.
        public ?int $seconds,
        public bool $sleeps,
    ) {}
}
