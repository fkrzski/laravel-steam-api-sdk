<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Events;

/**
 * Dispatched for every response Steam sends, a 4xx, 5xx or 429 included.
 */
final readonly class SteamResponseReceived
{
    /**
     * @param  string  $method  the path, such as `ISteamUser/GetPlayerSummaries/v2`
     * @param  array<string, mixed>  $query  without the API key
     * @param  float  $duration  in seconds
     */
    public function __construct(
        public string $method,
        public array $query,
        public int $attempt,
        public int $status,
        public float $duration,
    ) {}
}
