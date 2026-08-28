<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\OwnedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBanFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGamesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockResponse;

mutates(SteamResponse::class);

function fakedId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function bodyOf(MockResponse $response): mixed
{
    return $response->body()->all();
}

// Envelopes

it('nests player summaries under the response key', function (): void {
    expect(bodyOf(SteamResponse::playerSummaries(
        PlayerSummaryFactory::new()->personaName('Gabe'),
        PlayerSummaryFactory::new()->personaName('Newell'),
    )))->toBe([
        'response' => [
            'players' => [
                PlayerSummaryFactory::new()->personaName('Gabe')->toArray(),
                PlayerSummaryFactory::new()->personaName('Newell')->toArray(),
            ],
        ],
    ]);
});

it('puts ban records at the top level, without the response wrapper', function (): void {
    expect(bodyOf(SteamResponse::playerBans(PlayerBanFactory::new()->vacBanned(2))))->toBe([
        'players' => [PlayerBanFactory::new()->vacBanned(2)->toArray()],
    ]);
});

it('nests friends under the friendslist key', function (): void {
    expect(bodyOf(SteamResponse::friendList(FriendFactory::new())))->toBe([
        'friendslist' => ['friends' => [FriendFactory::new()->toArray()]],
    ]);
});

it('flags the group list as successful', function (): void {
    expect(bodyOf(SteamResponse::userGroupList(UserGroupFactory::new())))->toBe([
        'response' => [
            'success' => true,
            'groups' => [UserGroupFactory::new()->toArray()],
        ],
    ]);
});

it('counts the games it wraps', function (): void {
    expect(bodyOf(SteamResponse::ownedGames(
        OwnedGameFactory::new()->appId(381210),
        OwnedGameFactory::new()->appId(730),
    )))->toBe([
        'response' => [
            'game_count' => 2,
            'games' => [
                OwnedGameFactory::new()->appId(381210)->toArray(),
                OwnedGameFactory::new()->appId(730)->toArray(),
            ],
        ],
    ]);
});

it('reports a game count of zero for a player who owns nothing', function (): void {
    expect(bodyOf(SteamResponse::ownedGames()))->toBe([
        'response' => ['game_count' => 0, 'games' => []],
    ]);
});

it('nests recently played games under the response key', function (): void {
    expect(bodyOf(SteamResponse::recentlyPlayedGames(
        RecentlyPlayedGamesFactory::new()->games(
            RecentlyPlayedGameFactory::new()->appId(381210),
            RecentlyPlayedGameFactory::new()->appId(730),
        ),
    )))->toBe([
        'response' => [
            'total_count' => 2,
            'games' => [
                RecentlyPlayedGameFactory::new()->appId(381210)->toArray(),
                RecentlyPlayedGameFactory::new()->appId(730)->toArray(),
            ],
        ],
    ]);
});

it('nests the steam level under the response key', function (): void {
    expect(bodyOf(SteamResponse::steamLevel(42)))->toBe([
        'response' => ['player_level' => 42],
    ]);
});

it('nests user stats under the playerstats key', function (): void {
    expect(bodyOf(SteamResponse::userStats(UserStatsFactory::new())))->toBe([
        'playerstats' => UserStatsFactory::new()->toArray(),
    ]);
});

it('nests player achievements under the playerstats key', function (): void {
    expect(bodyOf(SteamResponse::playerAchievements(PlayerAchievementsFactory::new())))->toBe([
        'playerstats' => PlayerAchievementsFactory::new()->toArray(),
    ]);
});

it('reports a resolved vanity url as successful', function (): void {
    expect(bodyOf(SteamResponse::vanityUrl(fakedId())))->toBe([
        'response' => [
            'success' => 1,
            'steamid' => '76561198000000000',
        ],
    ]);
});

it('refuses a request outright with a 401', function (): void {
    expect(SteamResponse::profileNotPublic()->status())->toBe(401)
        ->and(bodyOf(SteamResponse::profileNotPublic()))->toBe(['message' => 'Access is denied.']);
});

it('hides a player service result behind a 200 with an empty response', function (): void {
    expect(SteamResponse::playerServiceNotPublic()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::playerServiceNotPublic()))->toBe(['response' => []]);
});

it('refuses stats with a 400 carrying an empty json object', function (): void {
    expect(SteamResponse::statsRefused()->status())->toBe(400)
        ->and(bodyOf(SteamResponse::statsRefused()))->toBe('{}');
});

it('reports an unclaimed vanity url in the body, not the status', function (): void {
    expect(SteamResponse::vanityNotFound()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::vanityNotFound()))->toBe([
            'response' => [
                'success' => 42,
                'message' => 'No match',
            ],
        ]);
});

it('answers a rejected key with a 403 echoing the key parameter', function (): void {
    expect(SteamResponse::invalidApiKey()->status())->toBe(403)
        ->and(bodyOf(SteamResponse::invalidApiKey()))->toContain('key=');
});

it('answers a missing key with a 400 in html', function (): void {
    expect(SteamResponse::apiKeyMissing()->status())->toBe(400)
        ->and(bodyOf(SteamResponse::apiKeyMissing()))->toContain("Parameter 'key' is missing");
});

// Round trips through the facade

it('feeds player summaries back through the facade', function (): void {
    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new()->personaName('Gabe')->online(),
        ),
    ]);

    $summaries = Steam::playerSummaries([fakedId()]);

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]->personaName)->toBe('Gabe');
});

it('feeds ban records back through the facade', function (): void {
    Steam::fake([
        GetPlayerBansRequest::class => SteamResponse::playerBans(PlayerBanFactory::new()->vacBanned(2)),
    ]);

    expect(Steam::playerBans([fakedId()])[0]->numberOfVacBans)->toBe(2);
});

it('feeds a friend list back through the facade', function (): void {
    Steam::fake([
        GetFriendListRequest::class => SteamResponse::friendList(FriendFactory::new(), FriendFactory::new()),
    ]);

    expect(Steam::friendList(fakedId()))->toHaveCount(2);
});

it('feeds a group list back through the facade', function (): void {
    Steam::fake([
        GetUserGroupListRequest::class => SteamResponse::userGroupList(UserGroupFactory::new()->gid('103582791429521413')),
    ]);

    expect(Steam::userGroupList(fakedId())[0]->gid)->toBe('103582791429521413');
});

it('feeds owned games back through the facade', function (): void {
    Steam::fake([
        GetOwnedGamesRequest::class => SteamResponse::ownedGames(OwnedGameFactory::new()->withAppInfo()),
    ]);

    expect(Steam::ownedGames(fakedId())[0]->name)->toBe('Dead by Daylight');
});

it('feeds recently played games back through the facade', function (): void {
    Steam::fake([
        GetRecentlyPlayedGamesRequest::class => SteamResponse::recentlyPlayedGames(
            RecentlyPlayedGamesFactory::new()->games(
                RecentlyPlayedGameFactory::new()->name('Counter-Strike 2'),
            ),
        ),
    ]);

    expect(Steam::recentlyPlayedGames(fakedId())->games[0]->name)->toBe('Counter-Strike 2');
});

it('feeds the steam level back through the facade', function (): void {
    Steam::fake([GetSteamLevelRequest::class => SteamResponse::steamLevel(42)]);

    expect(Steam::steamLevel(fakedId()))->toBe(42);
});

it('feeds user stats back through the facade', function (): void {
    Steam::fake([
        GetUserStatsForGameRequest::class => SteamResponse::userStats(UserStatsFactory::new()),
    ]);

    expect(Steam::userStatsForGame(fakedId(), appId: 381210)->stats)->toHaveCount(1);
});

it('feeds player achievements back through the facade', function (): void {
    Steam::fake([
        GetPlayerAchievementsRequest::class => SteamResponse::playerAchievements(
            PlayerAchievementsFactory::new()->achievements(
                PlayerAchievementFactory::new(),
                PlayerAchievementFactory::new()->locked(),
            ),
        ),
    ]);

    expect(Steam::playerAchievements(fakedId(), appId: 381210)->achievements)->toHaveCount(2);
});

it('feeds a resolved vanity url back through the facade', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(fakedId()),
    ]);

    expect(Steam::resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000');
});

// Failures — these pin the contract with the base SDK's exception mapping

it('raises a not public profile from a refused friend list', function (): void {
    Steam::fake([GetFriendListRequest::class => SteamResponse::profileNotPublic()]);

    expect(fn (): array => Steam::friendList(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises a not public profile from a refused group list', function (): void {
    Steam::fake([GetUserGroupListRequest::class => SteamResponse::profileNotPublic()]);

    expect(fn (): array => Steam::userGroupList(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises a not public profile from owned games without a game count', function (): void {
    Steam::fake([GetOwnedGamesRequest::class => SteamResponse::playerServiceNotPublic()]);

    expect(fn (): array => Steam::ownedGames(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises a not public profile from recently played games without a total count', function (): void {
    Steam::fake([GetRecentlyPlayedGamesRequest::class => SteamResponse::playerServiceNotPublic()]);

    expect(fn (): RecentlyPlayedGames => Steam::recentlyPlayedGames(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises a not public profile from a steam level without a player level', function (): void {
    Steam::fake([GetSteamLevelRequest::class => SteamResponse::playerServiceNotPublic()]);

    expect(fn (): int => Steam::steamLevel(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises a not public profile from refused user stats', function (): void {
    Steam::fake([GetUserStatsForGameRequest::class => SteamResponse::statsRefused()]);

    expect(fn () => Steam::userStatsForGame(fakedId(), appId: 381210))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises unavailable stats from refused player achievements', function (): void {
    Steam::fake([GetPlayerAchievementsRequest::class => SteamResponse::statsRefused()]);

    expect(fn () => Steam::playerAchievements(fakedId(), appId: 381210))
        ->toThrow(StatsUnavailableException::class);
});

it('raises a missing user from an unclaimed vanity url', function (): void {
    Steam::fake([ResolveVanityUrlRequest::class => SteamResponse::vanityNotFound()]);

    expect(fn (): SteamId => Steam::resolveVanityUrl('nobody'))
        ->toThrow(SteamUserNotFoundException::class);
});

it('raises an invalid api key from a rejected key', function (): void {
    Steam::fake([GetPlayerSummariesRequest::class => SteamResponse::invalidApiKey()]);

    expect(fn (): array => Steam::playerSummaries([fakedId()]))
        ->toThrow(InvalidApiKeyException::class);
});

it('raises an invalid api key from a request sent without one', function (): void {
    Steam::fake([GetPlayerSummariesRequest::class => SteamResponse::apiKeyMissing()]);

    expect(fn (): array => Steam::playerSummaries([fakedId()]))
        ->toThrow(InvalidApiKeyException::class);
});
