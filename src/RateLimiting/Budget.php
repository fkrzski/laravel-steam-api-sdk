<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\RateLimiting;

use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;
use Saloon\RateLimitPlugin\Limit;

/**
 * What is left of the connector's rate limits, and how long until the next
 * request may go.
 *
 * The connector is scoped and the renderer holding this outlives the scope, so
 * it is resolved on every call rather than kept.
 *
 * @internal
 */
final readonly class Budget
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * What is left of each limit counting requests, in the order the connector
     * checks them, read from the rate-limit store.
     *
     * @return array<Allowance>
     */
    public function remaining(): array
    {
        $connector = $this->connector();
        $store = $connector->rateLimitStore();

        return array_map(static function (Limit $limit) use ($store): Allowance {
            $limit->update($store);

            return new Allowance(
                allow: $limit->getAllow(),
                left: $limit->getAllow() - $limit->getHits(),
                seconds: str_ends_with($limit->getName(), UtcDayLimit::WINDOW) ? null : $limit->getReleaseInSeconds(),
                sleeps: $limit->getShouldSleep(),
            );
        }, $this->counting($connector));
    }

    /**
     * Seconds until the next request may go, or null while the budget has room.
     */
    public function wait(): ?int
    {
        $limit = $this->connector()->getExceededLimit();

        return $limit instanceof Limit ? $this->waitFor($limit) : null;
    }

    /**
     * Seconds until the limit that refused a request lets the next one go.
     */
    public function waitAfter(SteamRateLimitException $exception): int
    {
        return $this->waitFor($exception->limit);
    }

    private function connector(): SteamConnector
    {
        return $this->container->make(SteamConnector::class);
    }

    /**
     * The connector's limits without the 429 detector, which is response-driven
     * and carries no budget of its own.
     *
     * @return array<Limit>
     */
    private function counting(SteamConnector $connector): array
    {
        return array_filter($connector->getLimits(), static fn (Limit $limit): bool => ! $limit->usesResponse());
    }

    /**
     * Counted on Laravel's clock, which `travel()` reaches and the plugin's does not.
     *
     * The floor is a second: a closed window counts past zero, which would send a
     * job back before the counter rolls over and drop a falsy `Retry-After`.
     */
    private function waitFor(Limit $limit): int
    {
        return max(1, $limit->getExpiryTimestamp() - Date::now()->getTimestamp());
    }
}
