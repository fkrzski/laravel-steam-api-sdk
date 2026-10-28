<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppNewsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppVersionCheckFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;

mutates(SteamManager::class);

it('sends through the connector the fake sits on', function (): void {
    Steam::fake()
        ->players()->steamLevel(42)
        ->users()->summaries(PlayerSummaryFactory::new()->personaName('Gabe'))
        ->stats()->userStats(UserStatsFactory::new()->gameName('Dead by Daylight'))
        ->apps()->upToDateCheck(AppVersionCheckFactory::new())
        ->news()->appNews(AppNewsFactory::new()->total(3939))
        ->webApi()->serverInfo(new DateTimeImmutable('2026-10-08 19:59:38 UTC'));

    expect(Steam::players()->steamLevel(steamId()))->toBe(42)
        ->and(Steam::users()->summaries([steamId()])[0]->personaName)->toBe('Gabe')
        ->and(Steam::stats()->userStats(steamId(), appId: 381210)->gameName)->toBe('Dead by Daylight')
        ->and(Steam::apps()->upToDateCheck(appId: 440, version: 10828683)->isUpToDate)->toBeTrue()
        ->and(Steam::news()->appNews(appId: 440)->total)->toBe(3939)
        ->and(Steam::webApi()->serverTime()->getTimestamp())->toBe(1791489578);

    Steam::assertSentCount(6);
});

// A resource holds the connector it was built from, so one reached before the fake
// would send for real if the accessor ever built its own connector instead.
it('honours a fake installed after the resource was reached', function (): void {
    $players = Steam::players();

    Steam::fake()->players()->steamLevel(42);

    expect($players->steamLevel(steamId()))->toBe(42);

    Steam::assertSent(GetSteamLevelRequest::class);
});
