<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Events;

/**
 * Dispatched before every attempt at a Steam request, retries included.
 */
final readonly class SteamRequestSending
{
    /**
     * @param  string  $method  the path, such as `ISteamUser/GetPlayerSummaries/v2`
     * @param  array<string, mixed>  $query  without the API key
     */
    public function __construct(
        public string $method,
        public array $query,
        public int $attempt,
    ) {}
}
