<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing;

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\CommunityBadgeQuestFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\OwnedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBadgesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBanFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGamesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockResponse;

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

    public static function vanityUrl(SteamId $steamId): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => 1,
                'steamid' => $steamId->value,
            ],
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
     * Both ISteamUserStats endpoints answer 400 with an empty JSON object, which
     * `GetUserStatsForGame` raises as `ProfileNotPublicException` and
     * `GetPlayerAchievements` as `StatsUnavailableException` — the cause is
     * ambiguous, so the two requests read the same body differently.
     */
    public static function statsRefused(): MockResponse
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
}
