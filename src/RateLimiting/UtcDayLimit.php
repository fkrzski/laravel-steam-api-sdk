<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\RateLimiting;

use DateTimeImmutable;
use DateTimeZone;
use Saloon\RateLimitPlugin\Limit;

/**
 * A window closing at 00:00 UTC, named like the base SDK's daily budget so the
 * two share a counter.
 *
 * The provider builds it once per connector, so midnight is taken when the
 * window opens: pinned at build time, a job running past midnight would open
 * the next window 24 hours from its first request instead.
 *
 * @internal
 */
final class UtcDayLimit extends Limit
{
    public const string WINDOW = 'utc_midnight';

    public static function requests(int $allow): self
    {
        return self::allow($allow)->everySeconds(86_400, self::WINDOW);
    }

    public function getExpiryTimestamp(): int
    {
        return $this->expiryTimestamp ??= new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))->getTimestamp();
    }
}
