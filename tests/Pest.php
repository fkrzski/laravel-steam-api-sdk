<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\NewsItemFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\RateLimitPlugin\Limit;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->tia()->locally();

function steamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

/**
 * A feed of news items, newest first, with IDs counting up from `'1'` an hour apart.
 *
 * @return list<NewsItemFactory>
 */
function newsFeedItems(int $count): array
{
    return array_map(
        static fn (int $id): NewsItemFactory => NewsItemFactory::new()
            ->id((string) $id)
            ->publishedAt(new DateTimeImmutable('2026-10-01 12:00:00 UTC')->sub(new DateInterval(sprintf('PT%dH', $id)))),
        range(1, $count),
    );
}

/**
 * Write a spent daily budget into the store the connector reads, as a day of
 * traffic would.
 */
function spendDailyBudget(): void
{
    $connector = Steam::connector();

    $limit = array_find(
        $connector->getLimits(),
        static fn (Limit $limit): bool => ! $limit->usesResponse(),
    );

    $limit?->hit(100_000)->save($connector->rateLimitStore());
}

function fakeSteamEndpoints(): void
{
    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new(),
        ),
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(steamId()),
    ]);
}
