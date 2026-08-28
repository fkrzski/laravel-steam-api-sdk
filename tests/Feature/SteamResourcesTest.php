<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;

mutates(SteamManager::class);

it('sends through the connector the fake sits on', function (): void {
    Steam::fake([
        GetSteamLevelRequest::class => SteamResponse::steamLevel(42),
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new()->personaName('Gabe'),
        ),
        GetUserStatsForGameRequest::class => SteamResponse::userStats(
            UserStatsFactory::new()->gameName('Dead by Daylight'),
        ),
    ]);

    expect(Steam::players()->steamLevel(steamId()))->toBe(42)
        ->and(Steam::users()->summaries([steamId()])[0]->personaName)->toBe('Gabe')
        ->and(Steam::stats()->userStats(steamId(), appId: 381210)->gameName)->toBe('Dead by Daylight');

    Steam::assertSentCount(3);
});

// A resource holds the connector it was built from, so one reached before the fake
// would send for real if the accessor ever built its own connector instead.
it('honours a fake installed after the resource was reached', function (): void {
    $players = Steam::players();

    Steam::fake([
        GetSteamLevelRequest::class => SteamResponse::steamLevel(42),
    ]);

    expect($players->steamLevel(steamId()))->toBe(42);

    Steam::assertSent(GetSteamLevelRequest::class);
});

it('reaches the same endpoint through the flat helper and the resource', function (): void {
    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new()->personaName('Gabe'),
        ),
    ]);

    expect(Steam::summaries([steamId()]))->toEqual(Steam::users()->summaries([steamId()]));

    Steam::assertSentCount(2);
});
