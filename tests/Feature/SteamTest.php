<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Http\Requests\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;

mutates(SteamManager::class);

function steamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

it('registers the connector and manager as singletons', function (): void {
    expect(app(SteamConnector::class))->toBe(app(SteamConnector::class))
        ->and(app(SteamManager::class))->toBe(app(SteamManager::class));
});

it('merges the package config', function (): void {
    expect(config('steam-api.key'))->toBe('test-steam-api-key');
});

it('configures the connector with the api key', function (): void {
    expect(app(SteamConnector::class)->steamConfig->apiKey)->toBe('test-steam-api-key');
});

it('resolves the facade to the manager singleton', function (): void {
    expect(Steam::getFacadeRoot())->toBe(app(SteamManager::class));
});

it('exposes the underlying connector', function (): void {
    expect(Steam::connector())->toBe(app(SteamConnector::class));
});

it('fakes responses and resolves a vanity url', function (): void {
    $mock = Steam::fake([
        ResolveVanityUrlRequest::class => MockResponse::make([
            'response' => ['success' => 1, 'steamid' => '76561198000000000'],
        ]),
    ]);

    $steamId = Steam::resolveVanityUrl('gabelogannewell');

    expect($steamId)->toBeInstanceOf(SteamId::class)
        ->and($steamId->value
        )->toBe('76561198000000000');

    $mock->assertSent(ResolveVanityUrlRequest::class);
});

it('fetches player summaries through the convenience method', function (): void {
    $mock = Steam::fake([
        GetPlayerSummariesRequest::class => MockResponse::make([
            'response' => [
                'players' => [
                    [
                        'steamid' => '76561198000000000',
                        'personaname' => 'Gabe',
                        'profileurl' => 'https://steamcommunity.com/id/gabelogannewell/',
                        'avatar' => 'https://example.test/a.jpg',
                        'avatarmedium' => 'https://example.test/m.jpg',
                        'avatarfull' => 'https://example.test/f.jpg',
                        'avatarhash' => 'abc123',
                        'communityvisibilitystate' => 3,
                        'timecreated' => 1063407589,
                    ],
                ],
            ],
        ]),
    ]);

    $summaries = Steam::playerSummaries([SteamId::fromSteamId64('76561198000000000')]);

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0])->toBeInstanceOf(PlayerSummary::class)
        ->and($summaries[0]->personaName)->toBe('Gabe');

    $mock->assertSent(GetPlayerSummariesRequest::class);
});

it('fetches owned games', function (): void {
    $mock = Steam::fake([
        GetOwnedGamesRequest::class => MockResponse::make([
            'response' => [
                'game_count' => 1,
                'games' => [
                    ['appid' => 381210, 'playtime_forever' => 1200, 'playtime_2weeks' => 60],
                ],
            ],
        ]),
    ]);

    $games = Steam::ownedGames(steamId(), appIdsFilter: [381210]);

    expect($games)->toHaveCount(1)
        ->and($games[0])->toBeInstanceOf(OwnedGame::class)
        ->and($games[0]->appId)->toBe(381210);

    $mock->assertSent(GetOwnedGamesRequest::class);
});

it('fetches user stats for a game', function (): void {
    $mock = Steam::fake([
        GetUserStatsForGameRequest::class => MockResponse::make([
            'playerstats' => [
                'steamID' => '76561198000000000',
                'gameName' => 'Dead by Daylight',
                'stats' => [['name' => 'DBD_KillerSkulls', 'value' => 42]],
                'achievements' => [['name' => 'ACH_UNLOCK_KILLER_CHARACTER', 'achieved' => 1]],
            ],
        ]),
    ]);

    $stats = Steam::userStatsForGame(steamId(), appId: 381210);

    expect($stats)->toBeInstanceOf(UserStats::class)
        ->and($stats->gameName)->toBe('Dead by Daylight');

    $mock->assertSent(GetUserStatsForGameRequest::class);
});

it('fetches player achievements', function (): void {
    $mock = Steam::fake([
        GetPlayerAchievementsRequest::class => MockResponse::make([
            'playerstats' => [
                'steamID' => '76561198000000000',
                'gameName' => 'Dead by Daylight',
                'success' => true,
                'achievements' => [
                    ['apiname' => 'ACH_UNLOCK_KILLER_CHARACTER', 'achieved' => 1, 'unlocktime' => 1600000000],
                ],
            ],
        ]),
    ]);

    $achievements = Steam::playerAchievements(steamId(), appId: 381210);

    expect($achievements)->toBeInstanceOf(PlayerAchievements::class);

    $mock->assertSent(GetPlayerAchievementsRequest::class);
});

it('sends an arbitrary request and returns the raw response', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => MockResponse::make([
            'response' => ['success' => 1, 'steamid' => '76561198000000000'],
        ]),
    ]);

    $response = Steam::send(new ResolveVanityUrlRequest('gabelogannewell'));

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->status())->toBe(200);
});

it('sends requests concurrently through a pool', function (): void {
    Steam::fake([
        GetPlayerSummariesRequest::class => MockResponse::make([
            'response' => ['players' => []],
        ]),
    ]);

    $sent = [];

    Steam::pool(
        requests: [new GetPlayerSummariesRequest([steamId()])],
        concurrency: 1,
        responseHandler: function (Response $response) use (&$sent): void {
            $sent[] = $response->status();
        },
    )->send()->wait();

    expect($sent)->toBe([200]);
});
