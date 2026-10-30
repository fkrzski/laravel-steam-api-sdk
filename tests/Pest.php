<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\RateLimiting\Budget;
use Fkrzski\LaravelSteamApiSdk\SteamSettings;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerSummaryFactory;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\Wormhole;
use Saloon\Http\Senders\GuzzleSender;
use Tests\NightwatchTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->extend(NightwatchTestCase::class)->in('Nightwatch');

pest()->tia()->locally();

function steamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function steamSettings(): SteamSettings
{
    return app(SteamSettings::class);
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

function spendDailyBudget(): void
{
    app(Budget::class)->exhaust();
}

/**
 * The failure a faked 429 raises, read after its window has closed.
 */
function closedRateLimitWindow(): SteamRateLimitException
{
    Steam::fake()->tooManyRequests(60);

    try {
        Steam::stats()->currentPlayers(440);
    } catch (SteamRateLimitException $steamRateLimitException) {
        new Wormhole(90)->seconds();

        return $steamRateLimitException;
    }

    throw new LogicException('Expected the faked 429 to raise.');
}

function fakeSteamEndpoints(): void
{
    Steam::fake()
        ->users()->summaries(PlayerSummaryFactory::new())
        ->users()->resolveVanityUrl(steamId());
}

/**
 * Configure a GuzzleSender answering from the handler's queue instead of Steam.
 */
function bindGuzzleSender(MockHandler $handler): GuzzleSender
{
    $sender = new GuzzleSender;
    $sender->getHandlerStack()->setHandler($handler);

    app()->instance(GuzzleSender::class, $sender);
    config()->set('steam-api.http.sender', GuzzleSender::class);

    return $sender;
}

function vanityUrlAnswer(): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'response' => ['steamid' => steamId()->value, 'success' => 1],
    ], JSON_THROW_ON_ERROR));
}
