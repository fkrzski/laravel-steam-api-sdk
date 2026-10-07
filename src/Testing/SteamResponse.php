<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing;

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppNewsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppVersionCheckFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\CommunityBadgeQuestFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameSchemaFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameServerFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GlobalAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\OwnedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBadgesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBanFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGamesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SdrConfigFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\Exception\ConnectException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * Wraps the factory payloads in the envelope each Steam endpoint answers with.
 *
 * Every endpoint nests its collection differently — `GetPlayerBans` puts it at the
 * top level, `GetFriendList` under `friendslist.friends`, the rest under `response`
 * — and the failure shapes diverge just as far, so both live here rather than in
 * every test that fakes a call.
 */
final class SteamResponse
{
    public static function playerSummaries(PlayerSummaryFactory ...$players): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'players' => array_map(
                    static fn (PlayerSummaryFactory $player): array => $player->toArray(),
                    $players,
                ),
            ],
        ]);
    }

    /**
     * Ban records sit at the top level, without the `response` wrapper every other
     * ISteamUser endpoint uses.
     */
    public static function playerBans(PlayerBanFactory ...$bans): MockResponse
    {
        return MockResponse::make([
            'players' => array_map(
                static fn (PlayerBanFactory $ban): array => $ban->toArray(),
                $bans,
            ),
        ]);
    }

    public static function friendList(FriendFactory ...$friends): MockResponse
    {
        return MockResponse::make([
            'friendslist' => [
                'friends' => array_map(
                    static fn (FriendFactory $friend): array => $friend->toArray(),
                    $friends,
                ),
            ],
        ]);
    }

    public static function userGroupList(UserGroupFactory ...$groups): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => true,
                'groups' => array_map(
                    static fn (UserGroupFactory $group): array => $group->toArray(),
                    $groups,
                ),
            ],
        ]);
    }

    /**
     * `game_count` is what tells the SDK the profile is public — see
     * {@see self::playerServiceNotPublic()} for the response that omits it.
     */
    public static function ownedGames(OwnedGameFactory ...$games): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'game_count' => count($games),
                'games' => array_map(
                    static fn (OwnedGameFactory $game): array => $game->toArray(),
                    $games,
                ),
            ],
        ]);
    }

    /**
     * `total_count` is Steam's own total for the window, so it is independent of how
     * many games the payload lists — see {@see RecentlyPlayedGamesFactory::totalCount()}.
     */
    public static function recentlyPlayedGames(RecentlyPlayedGamesFactory $games): MockResponse
    {
        return MockResponse::make([
            'response' => $games->toArray(),
        ]);
    }

    public static function steamLevel(int $level): MockResponse
    {
        return MockResponse::make([
            'response' => ['player_level' => $level],
        ]);
    }

    /**
     * `player_level` is what tells the SDK the profile is public — a hidden one
     * answers with the empty `response` object {@see self::playerServiceNotPublic()}
     * returns.
     */
    public static function badges(PlayerBadgesFactory $badges): MockResponse
    {
        return MockResponse::make([
            'response' => $badges->toArray(),
        ]);
    }

    public static function communityBadgeProgress(CommunityBadgeQuestFactory ...$quests): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'quests' => array_map(
                    static fn (CommunityBadgeQuestFactory $quest): array => $quest->toArray(),
                    $quests,
                ),
            ],
        ]);
    }

    public static function userStats(UserStatsFactory $stats): MockResponse
    {
        return MockResponse::make([
            'playerstats' => $stats->toArray(),
        ]);
    }

    public static function playerAchievements(PlayerAchievementsFactory $achievements): MockResponse
    {
        return MockResponse::make([
            'playerstats' => $achievements->toArray(),
        ]);
    }

    public static function currentPlayers(int $count): MockResponse
    {
        return MockResponse::make([
            'response' => ['player_count' => $count],
        ]);
    }

    public static function globalAchievements(GlobalAchievementFactory ...$achievements): MockResponse
    {
        return MockResponse::make([
            'achievementpercentages' => [
                'achievements' => array_map(
                    static fn (GlobalAchievementFactory $achievement): array => $achievement->toArray(),
                    $achievements,
                ),
            ],
        ]);
    }

    public static function gameSchema(GameSchemaFactory $schema): MockResponse
    {
        return MockResponse::make([
            'game' => $schema->toArray(),
        ]);
    }

    public static function vanityUrl(SteamId $steamId): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => 1,
                'steamid' => $steamId->value,
            ],
        ]);
    }

    public static function upToDateCheck(AppVersionCheckFactory $check): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => true,
                ...$check->toArray(),
            ],
        ]);
    }

    /**
     * An address with no servers on it still succeeds, and Steam says so in a
     * `message` it sends only then.
     */
    public static function serversAtAddress(GameServerFactory ...$servers): MockResponse
    {
        $payload = [
            'success' => true,
            'servers' => array_map(
                static fn (GameServerFactory $server): array => $server->toArray(),
                $servers,
            ),
        ];

        if ($servers === []) {
            $payload['message'] = 'No servers found at that address';
        }

        return MockResponse::make([
            'response' => $payload,
        ]);
    }

    /**
     * The config sits at the top level, without the `response` wrapper, beside
     * the `success` flag Steam sends with it.
     */
    public static function sdrConfig(SdrConfigFactory $config): MockResponse
    {
        return MockResponse::make([
            ...$config->toArray(),
            'success' => true,
        ]);
    }

    /**
     * `count` is Steam's own total for the filter, so it is independent of how
     * many items the payload lists — see {@see AppNewsFactory::total()}.
     */
    public static function appNews(AppNewsFactory $news): MockResponse
    {
        return MockResponse::make([
            'appnews' => $news->toArray(),
        ]);
    }

    /**
     * A profile that refuses the request outright, raising `ProfileNotPublicException`
     * from `GetFriendList`, `GetUserGroupList` and `GetPlayerSummaries`.
     */
    public static function profileNotPublic(): MockResponse
    {
        return MockResponse::make(['message' => 'Access is denied.'], 401);
    }

    /**
     * Every IPlayerService endpoint answers a hidden profile with 200 and an empty
     * `response` object, dropping only the key it reads — `game_count`, `total_count`,
     * `player_level`. Nothing but that absence separates it from a player who owns or
     * played nothing, so this cannot be expressed as a status code.
     */
    public static function playerServiceNotPublic(): MockResponse
    {
        return MockResponse::make(['response' => []]);
    }

    /**
     * `GetUserStatsForGame` and `GetPlayerAchievements` both answer 400 with an empty
     * JSON object, which the first raises as `ProfileNotPublicException` and the
     * second as `StatsUnavailableException` — the cause is ambiguous, so the two
     * requests read the same body differently.
     */
    public static function statsRefused(): MockResponse
    {
        return MockResponse::make('{}', 400);
    }

    /**
     * An app ID Steam does not know, which `GetNumberOfCurrentPlayers` raises as
     * `AppNotFoundException`. The request reads the status alone; `result: 42` is
     * here because that is what Steam sends with it.
     */
    public static function appNotFound(): MockResponse
    {
        return MockResponse::make(['response' => ['result' => 42]], 404);
    }

    /**
     * `GetGlobalAchievementPercentagesForApp` answers 403 with an empty JSON object
     * for a game carrying no achievements and for a game ID it does not know alike,
     * raising `StatsUnavailableException` either way. The request claims this status
     * before the connector can read it as a rejected key or a hidden profile.
     */
    public static function globalAchievementsRefused(): MockResponse
    {
        return MockResponse::make('{}', 403);
    }

    /**
     * An app ID `GetSchemaForGame` does not know, which it raises as
     * `AppNotFoundException`. Byte for byte what {@see self::statsRefused()} returns,
     * because Steam answers both with a bare 400 — the two keep their own names since
     * nothing but the endpoint says which failure a test is faking.
     */
    public static function schemaAppNotFound(): MockResponse
    {
        return MockResponse::make('{}', 400);
    }

    /**
     * `ResolveVanityURL` reports an unclaimed slug in the body, not the status.
     */
    public static function vanityNotFound(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => 42,
                'message' => 'No match',
            ],
        ]);
    }

    /**
     * `UpToDateCheck` answers 200 with `success: false` alike for an app ID Steam
     * does not know and for an app running no versioned servers, raising
     * `AppVersionUnavailableException` either way.
     */
    public static function appVersionUnavailable(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => false,
                'error' => "Couldn't get app info for the app specified.",
            ],
        ]);
    }

    /**
     * An address `GetServersAtAddress` rejects, raising `InvalidServerAddressException`.
     * The request tells it apart from {@see self::serversAtAddressRefused()} by the
     * `'addr' param` in the message alone.
     */
    public static function invalidServerAddress(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => false,
                'message' => "'addr' param should specify a valid IPv4 or IPv4:queryport",
            ],
        ]);
    }

    /**
     * An address `GetServersAtAddress` refuses to look up at all. The base SDK raises
     * it as the root `SteamApiException`, so nothing in the type names this failure.
     */
    public static function serversAtAddressRefused(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => false,
                'message' => "Please don't call this API more often than once per minute for a given IP.",
            ],
        ]);
    }

    /**
     * An app ID `GetSDRConfig` does not know, the third shape of it after
     * {@see self::appNotFound()} and {@see self::schemaAppNotFound()}. The request
     * raises `AppNotFoundException` before the connector can retry the 500.
     */
    public static function sdrConfigAppNotFound(): MockResponse
    {
        return MockResponse::make([
            'success' => false,
            'message' => 'Failed to get appinfo',
        ], 500);
    }

    /**
     * `GetNewsForApp` answers 403 with an empty JSON object for an app ID it does
     * not know and for some apps that exist, such as Spacewar, raising
     * `AppNewsUnavailableException` either way. The request claims this status
     * before the connector can read it as a rejected key or a hidden profile.
     */
    public static function appNewsUnavailable(): MockResponse
    {
        return MockResponse::make('{}', 403);
    }

    /**
     * A key Steam rejects. The connector matches on `key=` in the body, so the
     * echoed query string is what makes this an `InvalidApiKeyException`.
     */
    public static function invalidApiKey(): MockResponse
    {
        return MockResponse::make(
            '<html><head><title>Forbidden</title></head><body><h1>Forbidden</h1>Access is denied. Retrying will not help. Please verify your <pre>key=</pre> parameter.</body></html>',
            403,
        );
    }

    /**
     * The same rejected key on 401 instead of 403. Which status a key error
     * lands on is the endpoint's choice — `GetCommunityBadgeProgress` picks
     * this one, and without the `key=` marker it would read as a hidden profile.
     */
    public static function apiKeyUnauthorized(): MockResponse
    {
        return MockResponse::make(
            '<html><head><title>Unauthorized</title></head><body><h1>Unauthorized</h1>Access is denied. Retrying will not help. Please verify your <pre>key=</pre> parameter.</body></html>',
            401,
        );
    }

    /**
     * A request that reached Steam without a key at all. Steam answers in HTML
     * rather than JSON, which is how the connector tells it apart from a real
     * API error on the same status.
     */
    public static function apiKeyMissing(): MockResponse
    {
        return MockResponse::make(
            "<html><head><title>Bad Request</title></head><body><h1>Bad Request</h1>Please verify that all required parameters are being sent.<pre>Parameter 'key' is missing</pre></body></html>",
            400,
        );
    }

    /**
     * A request that never reaches Steam, retried like a real outage and raised as
     * `SteamConnectionException` once the tries are spent. Saloon throws it before
     * recording a response, so `Steam::recorded()` and the `assertSent` family never
     * see the attempt.
     */
    public static function connectionFailed(): MockResponse
    {
        return MockResponse::make()->throw(
            static fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(
                new ConnectException(
                    'cURL error 28: Operation timed out after 10000 milliseconds',
                    $pendingRequest->createPsrRequest(),
                ),
                $pendingRequest,
            ),
        );
    }
}
