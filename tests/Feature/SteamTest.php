<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\SteamApiKeyMissingException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
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

it('trims surrounding whitespace from the api key', function (): void {
    config()->set('steam-api.key', '  test-steam-api-key  ');

    expect(app(SteamConnector::class)->steamConfig->apiKey)->toBe('test-steam-api-key');
});

it('throws when the api key is missing', function (mixed $key): void {
    config()->set('steam-api.key', $key);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(SteamApiKeyMissingException::class);
})->with([
    'null' => null,
    'empty string' => '',
    'whitespace only' => '   ',
    'integer' => 123,
    'array' => [[]],
    'boolean' => true,
]);

it('names the env var and the config key in the exception', function (): void {
    config()->set(['steam-api.key' => null]);

    $resolve = fn (): SteamConnector => app(SteamConnector::class);

    expect($resolve)->toThrow(SteamApiKeyMissingException::class, 'STEAM_API_KEY')
        ->and($resolve)->toThrow(SteamApiKeyMissingException::class, 'steam-api.key')
        ->and(new SteamApiKeyMissingException)->toBeInstanceOf(SteamApiException::class);
});

it('does not require the api key until the connector is built', function (): void {
    config()->set(['steam-api.key' => null]);

    expect(app(SteamManager::class))->toBeInstanceOf(SteamManager::class)
        ->and(fn (): SteamConnector => Steam::connector())
        ->toThrow(SteamApiKeyMissingException::class);
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

it('fetches a friend list', function (): void {
    $mock = Steam::fake([
        GetFriendListRequest::class => MockResponse::make([
            'friendslist' => [
                'friends' => [
                    [
                        'steamid' => '76561198000000001',
                        'relationship' => 'friend',
                        'friend_since' => 1600000000,
                    ],
                ],
            ],
        ]),
    ]);

    $friends = Steam::friendList(steamId());

    expect($friends)->toHaveCount(1)
        ->and($friends[0])->toBeInstanceOf(Friend::class)
        ->and($friends[0]->steamId->value)->toBe('76561198000000001')
        ->and($friends[0]->relationship)->toBe(FriendRelationship::Friend)
        ->and($friends[0]->friendSince->getTimestamp())->toBe(1600000000);

    $mock->assertSent(GetFriendListRequest::class);
});

it('sends no relationship filter by default', function (): void {
    $mock = Steam::fake([
        GetFriendListRequest::class => MockResponse::make(['friendslist' => ['friends' => []]]),
    ]);

    expect(Steam::friendList(steamId()))->toBe([]);

    $mock->assertSent(
        fn (GetFriendListRequest $request): bool => ! $request->relationship instanceof FriendRelationship,
    );
});

it('narrows the friend list to a relationship', function (): void {
    $mock = Steam::fake([
        GetFriendListRequest::class => MockResponse::make(['friendslist' => ['friends' => []]]),
    ]);

    Steam::friendList(steamId(), FriendRelationship::All);

    $mock->assertSent(
        fn (GetFriendListRequest $request): bool => $request->relationship === FriendRelationship::All,
    );
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
