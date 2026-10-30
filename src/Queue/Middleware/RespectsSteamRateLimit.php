<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Queue\Middleware;

use Closure;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\Budget;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Illuminate\Container\Container;

/**
 * Releases a queued job for what is left of the Steam rate limit window,
 * instead of letting it fail or holding the worker while it waits.
 *
 * The job is held back before it runs once the connector's limit is spent, and
 * released again when a request inside it raises {@see SteamRateLimitException}.
 * The wait is read off the connector's budget rather than Laravel's
 * `RateLimiter`, a counter of its own that would drift from the one the plugin
 * keeps.
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

        $budget = Container::getInstance()->make(Budget::class);
        $wait = $budget->wait();

        if ($wait !== null) {
            return $job->release($wait);
        }

        try {
            return $next($job);
        } catch (SteamRateLimitException $steamRateLimitException) {
            return $job->release($budget->waitAfter($steamRateLimitException));
        }
    }
}
