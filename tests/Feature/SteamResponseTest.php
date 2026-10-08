<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppNewsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppVersionCheckFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\BadgeFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\CommunityBadgeQuestFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameSchemaFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameServerFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GlobalAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\NewsItemFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\OwnedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBadgesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBanFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGamesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SdrConfigFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SdrPointOfPresenceFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
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

it('nests badges under the response key', function (): void {
    expect(bodyOf(SteamResponse::badges(PlayerBadgesFactory::new())))->toBe([
        'response' => PlayerBadgesFactory::new()->toArray(),
    ]);
});

it('nests community badge quests under the response key', function (): void {
    expect(bodyOf(SteamResponse::communityBadgeProgress(
        CommunityBadgeQuestFactory::new(),
        CommunityBadgeQuestFactory::new()->questId(202)->incomplete(),
    )))->toBe([
        'response' => [
            'quests' => [
                CommunityBadgeQuestFactory::new()->toArray(),
                CommunityBadgeQuestFactory::new()->questId(202)->incomplete()->toArray(),
            ],
        ],
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

it('nests the player count under the response key', function (): void {
    expect(bodyOf(SteamResponse::currentPlayers(12_345)))->toBe([
        'response' => ['player_count' => 12_345],
    ]);
});

it('nests global achievements under the achievementpercentages key', function (): void {
    expect(bodyOf(SteamResponse::globalAchievements(
        GlobalAchievementFactory::new(),
        GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5),
    )))->toBe([
        'achievementpercentages' => [
            'achievements' => [
                GlobalAchievementFactory::new()->toArray(),
                GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5)->toArray(),
            ],
        ],
    ]);
});

it('nests the game schema under the game key', function (): void {
    expect(bodyOf(SteamResponse::gameSchema(GameSchemaFactory::new())))->toBe([
        'game' => GameSchemaFactory::new()->toArray(),
    ]);
});

it('answers an app publishing no schema with an empty game object', function (): void {
    expect(bodyOf(SteamResponse::gameSchema(GameSchemaFactory::new()->empty())))->toBe([
        'game' => [],
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

it('flags the version check as successful', function (): void {
    expect(bodyOf(SteamResponse::upToDateCheck(AppVersionCheckFactory::new()->outOfDate())))->toBe([
        'response' => [
            'success' => true,
            ...AppVersionCheckFactory::new()->outOfDate()->toArray(),
        ],
    ]);
});

it('flags the server list as successful', function (): void {
    expect(bodyOf(SteamResponse::serversAtAddress(
        GameServerFactory::new(),
        GameServerFactory::new()->address('108.181.62.21:27025'),
    )))->toBe([
        'response' => [
            'success' => true,
            'servers' => [
                GameServerFactory::new()->toArray(),
                GameServerFactory::new()->address('108.181.62.21:27025')->toArray(),
            ],
        ],
    ]);
});

it('answers an address with no servers with steams message', function (): void {
    expect(bodyOf(SteamResponse::serversAtAddress()))->toBe([
        'response' => [
            'success' => true,
            'servers' => [],
            'message' => 'No servers found at that address',
        ],
    ]);
});

it('puts the sdr config at the top level beside a success flag', function (): void {
    expect(bodyOf(SteamResponse::sdrConfig(SdrConfigFactory::new())))->toBe([
        ...SdrConfigFactory::new()->toArray(),
        'success' => true,
    ]);
});

it('nests app news under the appnews key', function (): void {
    expect(bodyOf(SteamResponse::appNews(
        AppNewsFactory::new()->items(NewsItemFactory::new())->total(3939),
    )))->toBe([
        'appnews' => [
            'appid' => 440,
            'newsitems' => [NewsItemFactory::new()->toArray()],
            'count' => 3939,
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

it('reports an unknown app with a 404 carrying result 42', function (): void {
    expect(SteamResponse::appNotFound()->status())->toBe(404)
        ->and(bodyOf(SteamResponse::appNotFound()))->toBe(['response' => ['result' => 42]]);
});

it('refuses global achievements with a 403 carrying an empty json object', function (): void {
    expect(SteamResponse::globalAchievementsRefused()->status())->toBe(403)
        ->and(bodyOf(SteamResponse::globalAchievementsRefused()))->toBe('{}');
});

it('reports an app the schema endpoint does not know with a bare 400', function (): void {
    expect(SteamResponse::schemaAppNotFound()->status())->toBe(400)
        ->and(bodyOf(SteamResponse::schemaAppNotFound()))->toBe('{}');
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

it('reports an unavailable version check in the body, not the status', function (): void {
    expect(SteamResponse::appVersionUnavailable()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::appVersionUnavailable()))->toBe([
            'response' => [
                'success' => false,
                'error' => "Couldn't get app info for the app specified.",
            ],
        ]);
});

it('reports a rejected server address in the body, not the status', function (): void {
    expect(SteamResponse::invalidServerAddress()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::invalidServerAddress()))->toBe([
            'response' => [
                'success' => false,
                'message' => "'addr' param should specify a valid IPv4 or IPv4:queryport",
            ],
        ]);
});

it('reports a refused server lookup in the body, not the status', function (): void {
    expect(SteamResponse::serversAtAddressRefused()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::serversAtAddressRefused()))->toBe([
            'response' => [
                'success' => false,
                'message' => "Please don't call this API more often than once per minute for a given IP.",
            ],
        ]);
});

it('reports an app the sdr config does not know with a 500', function (): void {
    expect(SteamResponse::sdrConfigAppNotFound()->status())->toBe(500)
        ->and(bodyOf(SteamResponse::sdrConfigAppNotFound()))->toBe([
            'success' => false,
            'message' => 'Failed to get appinfo',
        ]);
});

it('refuses app news with a 403 carrying an empty json object', function (): void {
    expect(SteamResponse::appNewsUnavailable()->status())->toBe(403)
        ->and(bodyOf(SteamResponse::appNewsUnavailable()))->toBe('{}');
});

it('answers a rejected key with a 403 echoing the key parameter', function (): void {
    expect(SteamResponse::invalidApiKey()->status())->toBe(403)
        ->and(bodyOf(SteamResponse::invalidApiKey()))->toContain('key=');
});

it('answers a rejected key with a 401 echoing the key parameter', function (): void {
    expect(SteamResponse::apiKeyUnauthorized()->status())->toBe(401)
        ->and(bodyOf(SteamResponse::apiKeyUnauthorized()))->toContain('key=');
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

    $summaries = Steam::summaries([fakedId()]);

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]->personaName)->toBe('Gabe');
});

it('feeds ban records back through the facade', function (): void {
    Steam::fake([
        GetPlayerBansRequest::class => SteamResponse::playerBans(PlayerBanFactory::new()->vacBanned(2)),
    ]);

    expect(Steam::bans([fakedId()])[0]->numberOfVacBans)->toBe(2);
});

it('feeds a friend list back through the facade', function (): void {
    Steam::fake([
        GetFriendListRequest::class => SteamResponse::friendList(FriendFactory::new(), FriendFactory::new()),
    ]);

    expect(Steam::friends(fakedId()))->toHaveCount(2);
});

it('feeds a group list back through the facade', function (): void {
    Steam::fake([
        GetUserGroupListRequest::class => SteamResponse::userGroupList(UserGroupFactory::new()->gid('103582791429521413')),
    ]);

    expect(Steam::groups(fakedId())[0]->gid)->toBe('103582791429521413');
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

it('feeds badges back through the facade', function (): void {
    Steam::fake([
        GetBadgesRequest::class => SteamResponse::badges(
            PlayerBadgesFactory::new()->badges(BadgeFactory::new()->communityItem()),
        ),
    ]);

    $badges = Steam::badges(fakedId());

    expect($badges->playerLevel)->toBe(12)
        ->and($badges->badges[0]->communityItemId)->toBe('2101234567890123456');
});

it('feeds community badge progress back through the facade', function (): void {
    Steam::fake([
        GetCommunityBadgeProgressRequest::class => SteamResponse::communityBadgeProgress(
            CommunityBadgeQuestFactory::new(),
            CommunityBadgeQuestFactory::new()->questId(202)->incomplete(),
        ),
    ]);

    $quests = Steam::communityBadgeProgress(fakedId());

    expect($quests)->toHaveCount(2)
        ->and($quests[1]->questId)->toBe(202)
        ->and($quests[1]->completed)->toBeFalse();
});

it('feeds user stats back through the facade', function (): void {
    Steam::fake([
        GetUserStatsForGameRequest::class => SteamResponse::userStats(UserStatsFactory::new()),
    ]);

    expect(Steam::userStats(fakedId(), appId: 381210)->stats)->toHaveCount(1);
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

    expect(Steam::achievements(fakedId(), appId: 381210)->achievements)->toHaveCount(2);
});

it('feeds the player count back through the facade', function (): void {
    Steam::fake([
        GetNumberOfCurrentPlayersRequest::class => SteamResponse::currentPlayers(12_345),
    ]);

    expect(Steam::currentPlayers(appId: 381210))->toBe(12_345);
});

it('feeds global achievements back through the facade', function (): void {
    Steam::fake([
        GetGlobalAchievementPercentagesForAppRequest::class => SteamResponse::globalAchievements(
            GlobalAchievementFactory::new(),
            GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5),
        ),
    ]);

    $achievements = Steam::globalAchievements(gameId: 381210);

    expect($achievements)->toHaveCount(2)
        ->and($achievements[1]->apiName)->toBe('ACH_ESCAPE')
        ->and($achievements[1]->percent)->toBe(12.5);
});

it('feeds the game schema back through the facade', function (): void {
    Steam::fake([
        GetSchemaForGameRequest::class => SteamResponse::gameSchema(
            GameSchemaFactory::new()->gameName('Portal 2'),
        ),
    ]);

    $schema = Steam::schema(appId: 620);

    expect($schema->gameName)->toBe('Portal 2')
        ->and($schema->stats)->toHaveCount(1)
        ->and($schema->achievements)->toHaveCount(1);
});

it('feeds a resolved vanity url back through the facade', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(fakedId()),
    ]);

    expect(Steam::resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000');
});

it('feeds a version check back through the facade', function (): void {
    Steam::fake([
        UpToDateCheckRequest::class => SteamResponse::upToDateCheck(AppVersionCheckFactory::new()->outOfDate()),
    ]);

    $check = Steam::upToDateCheck(appId: 440, version: 1);

    expect($check->isUpToDate)->toBeFalse()
        ->and($check->requiredVersion)->toBe(10828683)
        ->and($check->message)->toBe('Your server is out of date, please upgrade');
});

it('feeds a server list back through the facade', function (): void {
    Steam::fake([
        GetServersAtAddressRequest::class => SteamResponse::serversAtAddress(
            GameServerFactory::new()->withoutSpectatorPort(),
        ),
    ]);

    $servers = Steam::serversAtAddress('108.181.62.21');

    expect($servers)->toHaveCount(1)
        ->and($servers[0]->address)->toBe('108.181.62.21:27015')
        ->and($servers[0]->spectatorPort)->toBeNull();
});

it('feeds an sdr config back through the facade', function (): void {
    Steam::fake([
        GetSdrConfigRequest::class => SteamResponse::sdrConfig(SdrConfigFactory::new()->pointsOfPresence(
            SdrPointOfPresenceFactory::new()->code('waw')->latitude(52.22)->longitude(21),
            SdrPointOfPresenceFactory::new()->code('eat')->aliases('mwh')->withoutRelays(),
        )),
    ]);

    $pointsOfPresence = Steam::sdrConfig(appId: 730)->pointsOfPresence;

    expect(array_keys($pointsOfPresence))->toBe(['waw', 'eat'])
        ->and($pointsOfPresence['waw']->latitude)->toBe(52.22)
        ->and($pointsOfPresence['waw']->longitude)->toBe(21.0)
        ->and($pointsOfPresence['eat']->aliases)->toBe(['mwh'])
        ->and($pointsOfPresence['eat']->relays)->toBeEmpty();
});

it('feeds app news back through the facade', function (): void {
    Steam::fake([
        GetNewsForAppRequest::class => SteamResponse::appNews(
            AppNewsFactory::new()
                ->items(
                    NewsItemFactory::new()->communityAnnouncement()->tags('patchnotes'),
                    NewsItemFactory::new()->withoutAuthor(),
                )
                ->total(3939),
        ),
    ]);

    $news = Steam::appNews(appId: 440, count: 2);

    expect($news->total)->toBe(3939)
        ->and($news->items)->toHaveCount(2)
        ->and($news->items[0]->isCommunityAnnouncement)->toBeTrue()
        ->and($news->items[0]->tags)->toBe(['patchnotes'])
        ->and($news->items[1]->author)->toBeNull()
        ->and($news->items[1]->tags)->toBeEmpty();
});

it('pages a news feed the way steam does', function (): void {
    $items = newsFeedItems(3);

    Steam::fake([GetNewsForAppRequest::class => SteamResponse::newsFeed(...$items)]);

    $first = Steam::appNews(appId: 570, count: 2);
    $next = Steam::appNews(appId: 570, count: 2, endDate: $items[1]->make()->publishedAt);

    expect($first->appId)->toBe(570)
        ->and(array_column($first->items, 'id'))->toBe(['1', '2'])
        ->and($first->total)->toBe(3)
        ->and(array_column($next->items, 'id'))->toBe(['2', '3'])
        ->and($next->total)->toBe(2);
});

it('answers a news feed request without a count with twenty items', function (): void {
    Steam::fake([GetNewsForAppRequest::class => SteamResponse::newsFeed(...newsFeedItems(21))]);

    $news = Steam::appNews(appId: 440);

    expect($news->items)->toHaveCount(20)
        ->and($news->total)->toBe(21);
});

it('answers an empty news feed with no news', function (): void {
    Steam::fake([GetNewsForAppRequest::class => SteamResponse::newsFeed()]);

    $news = Steam::appNews(appId: 440);

    expect($news->items)->toBeEmpty()
        ->and($news->total)->toBe(0);
});

it('refuses to answer anything but the news endpoint from a news feed', function (): void {
    Steam::fake([SteamConnector::class => SteamResponse::newsFeed()]);

    expect(fn (): int => Steam::currentPlayers(appId: 440))
        ->toThrow(LogicException::class, 'A faked news feed answers GetNewsForAppRequest only.');
});

// Failures — these pin the contract with the base SDK's exception mapping

it('raises a not public profile from a refused friend list', function (): void {
    Steam::fake([GetFriendListRequest::class => SteamResponse::profileNotPublic()]);

    expect(fn (): array => Steam::friends(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises a not public profile from a refused group list', function (): void {
    Steam::fake([GetUserGroupListRequest::class => SteamResponse::profileNotPublic()]);

    expect(fn (): array => Steam::groups(fakedId()))
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

it('raises a not public profile from badges without a player level', function (): void {
    Steam::fake([GetBadgesRequest::class => SteamResponse::playerServiceNotPublic()]);

    expect(fn (): PlayerBadges => Steam::badges(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises a not public profile from community badge progress without quests', function (): void {
    Steam::fake([GetCommunityBadgeProgressRequest::class => SteamResponse::playerServiceNotPublic()]);

    expect(fn (): array => Steam::communityBadgeProgress(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises a not public profile from refused user stats', function (): void {
    Steam::fake([GetUserStatsForGameRequest::class => SteamResponse::statsRefused()]);

    expect(fn () => Steam::userStats(fakedId(), appId: 381210))
        ->toThrow(ProfileNotPublicException::class);
});

it('raises unavailable stats from refused player achievements', function (): void {
    Steam::fake([GetPlayerAchievementsRequest::class => SteamResponse::statsRefused()]);

    expect(fn () => Steam::achievements(fakedId(), appId: 381210))
        ->toThrow(StatsUnavailableException::class);
});

it('raises a missing app from an unknown app id', function (): void {
    Steam::fake([GetNumberOfCurrentPlayersRequest::class => SteamResponse::appNotFound()]);

    expect(fn (): int => Steam::currentPlayers(appId: 1))
        ->toThrow(AppNotFoundException::class);
});

// The connector reads a 403 as a rejected key or a hidden profile; the request
// claims this one first, which is what the fake has to keep working.
it('raises unavailable stats from refused global achievements', function (): void {
    Steam::fake([
        GetGlobalAchievementPercentagesForAppRequest::class => SteamResponse::globalAchievementsRefused(),
    ]);

    expect(fn (): array => Steam::globalAchievements(gameId: 381210))
        ->toThrow(StatsUnavailableException::class);
});

it('raises a missing app from an app id the schema endpoint does not know', function (): void {
    Steam::fake([GetSchemaForGameRequest::class => SteamResponse::schemaAppNotFound()]);

    expect(fn (): GameSchema => Steam::schema(appId: 1))
        ->toThrow(AppNotFoundException::class);
});

it('raises a missing user from an unclaimed vanity url', function (): void {
    Steam::fake([ResolveVanityUrlRequest::class => SteamResponse::vanityNotFound()]);

    expect(fn (): SteamId => Steam::resolveVanityUrl('nobody'))
        ->toThrow(SteamUserNotFoundException::class);
});

it('raises an unavailable app version from an unsuccessful version check', function (): void {
    Steam::fake([UpToDateCheckRequest::class => SteamResponse::appVersionUnavailable()]);

    expect(fn (): AppVersionCheck => Steam::upToDateCheck(appId: 999999999, version: 1))
        ->toThrow(AppVersionUnavailableException::class);
});

it('raises an invalid server address from a rejected address', function (): void {
    Steam::fake([GetServersAtAddressRequest::class => SteamResponse::invalidServerAddress()]);

    expect(fn (): array => Steam::serversAtAddress('not-an-ip'))
        ->toThrow(InvalidServerAddressException::class);
});

// InvalidServerAddressException is a SteamApiException too, so only the message
// says the base fell through to the root type.
it('raises the root exception from a refused server lookup', function (): void {
    Steam::fake([GetServersAtAddressRequest::class => SteamResponse::serversAtAddressRefused()]);

    expect(fn (): array => Steam::serversAtAddress('127.0.0.1'))
        ->toThrow(SteamApiException::class, 'once per minute for a given IP');
});

it('raises a missing app from an app id the sdr config does not know', function (): void {
    Steam::fake([GetSdrConfigRequest::class => SteamResponse::sdrConfigAppNotFound()]);

    expect(fn (): SdrConfig => Steam::sdrConfig(appId: 999999999))
        ->toThrow(AppNotFoundException::class, 'No Steam app found for app ID 999999999.');
});

// Like global achievements, the request claims this 403 before the connector can
// read it as a rejected key or a hidden profile.
it('raises unavailable news from a 403 on the news endpoint', function (): void {
    Steam::fake([GetNewsForAppRequest::class => SteamResponse::appNewsUnavailable()]);

    expect(fn (): AppNews => Steam::appNews(appId: 480))
        ->toThrow(AppNewsUnavailableException::class, 'GetNewsForApp: Steam returned no news for app 480');
});

it('claims an app the sdr config does not know before the retry loop', function (): void {
    config()->set(['steam-api.http.retry.tries' => 3]);

    Steam::fake([GetSdrConfigRequest::class => SteamResponse::sdrConfigAppNotFound()]);

    expect(fn (): SdrConfig => Steam::sdrConfig(appId: 999999999))
        ->toThrow(AppNotFoundException::class);

    Steam::assertSentCount(1);
});

it('raises an invalid api key from a rejected key', function (): void {
    Steam::fake([GetPlayerSummariesRequest::class => SteamResponse::invalidApiKey()]);

    expect(fn (): array => Steam::summaries([fakedId()]))
        ->toThrow(InvalidApiKeyException::class);
});

it('raises an invalid api key from a key rejected with a 401', function (): void {
    Steam::fake([GetCommunityBadgeProgressRequest::class => SteamResponse::apiKeyUnauthorized()]);

    expect(fn (): array => Steam::communityBadgeProgress(fakedId()))
        ->toThrow(InvalidApiKeyException::class);
});

it('raises an invalid api key from a request sent without one', function (): void {
    Steam::fake([GetPlayerSummariesRequest::class => SteamResponse::apiKeyMissing()]);

    expect(fn (): array => Steam::summaries([fakedId()]))
        ->toThrow(InvalidApiKeyException::class);
});

it('raises a connection failure from a request that never reaches steam', function (): void {
    Steam::fake([GetPlayerSummariesRequest::class => SteamResponse::connectionFailed()]);

    expect(fn (): array => Steam::summaries([fakedId()]))->toThrow(
        SteamConnectionException::class,
        'Could not reach the Steam Web API: cURL error 28: Operation timed out after 10000 milliseconds',
    );
});

it('retries a connection failure without recording any attempt', function (): void {
    config()->set(['steam-api.http.retry.tries' => 3]);

    $attempts = 0;

    Steam::fake([
        GetPlayerSummariesRequest::class => function () use (&$attempts): MockResponse {
            $attempts++;

            return SteamResponse::connectionFailed();
        },
    ]);

    expect(fn (): array => Steam::summaries([fakedId()]))->toThrow(SteamConnectionException::class)
        ->and($attempts)->toBe(3)
        ->and(Steam::recorded())->toBeEmpty();
});
