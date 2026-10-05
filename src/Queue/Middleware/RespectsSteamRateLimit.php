<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Queue\Middleware;

use Closure;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Container\Container;
use Saloon\RateLimitPlugin\Limit;

/**
 * Releases a queued job for what is left of the Steam rate limit window,
 * instead of letting it fail or holding the worker while it waits.
 *
 * The job is held back before it runs once the connector's limit is spent, and
 * released again when a request inside it raises {@see SteamRateLimitException}.
 * The limit is read off the connector rather than Laravel's `RateLimiter`, a
 * counter of its own that would drift from the one the plugin keeps.
 *
 * A release counts as an attempt, so a job waiting out a daily window wants
 * `retryUntil()` rather than a small `$tries`.
 */
final readonly class RespectsSteamRateLimit
{
    public function handle(object $job, Closure $next): mixed
    {
        // Without InteractsWithQueue there is nothing to release, so the job runs unguarded.
        if (! method_exists($job, 'release')) {
            return $next($job);
        }

        $limit = Container::getInstance()->make(SteamConnector::class)->getExceededLimit();

        if ($limit instanceof Limit) {
            return $job->release($this->secondsLeft($limit));
        }

        try {
            return $next($job);
        } catch (SteamRateLimitException $steamRateLimitException) {
            return $job->release($this->secondsLeft($steamRateLimitException->limit));
        }
    }

    /**
     * A window closing this very second would release with no delay, and the job
     * would be back before the counter rolls over.
     */
    private function secondsLeft(Limit $limit): int
    {
        return max(1, $limit->getRemainingSeconds());
    }
}
