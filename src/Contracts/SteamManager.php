<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Contracts;

use Closure;
use DateTimeInterface;
use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeNotInstalledException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeOutsideTestsException;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest;
use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\GameServer;
use Fkrzski\SteamApiSdk\Dto\GlobalAchievement;
use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Http\Resources\AppsResource;
use Fkrzski\SteamApiSdk\Http\Resources\NewsResource;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Pool;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * What the `Steam` facade resolves.
 *
 * Bind this interface to swap the manager — the shipped class is `final`, so the
 * container is the seam.
 */
interface SteamManager
{
    /**
     * The underlying Saloon connector. Use this as an escape hatch for
     * advanced features (e.g. building custom requests) not exposed here.
     */
    public function connector(): SteamConnector;

    /**
     * Send a Steam request and return the raw Saloon response.
     */
    public function send(Request $request): Response;

    /**
     * Build a request pool for sending Steam requests concurrently.
     *
     * @param  iterable<Request>|callable  $requests
     */
    public function pool(
        iterable|callable $requests = [],
        int|callable $concurrency = 5,
        ?callable $responseHandler = null,
        ?callable $exceptionHandler = null,
    ): Pool;

    /**
     * The IPlayerService endpoints, reached fluently.
     */
    public function players(): PlayersResource;

    /**
     * The ISteamUser endpoints, reached fluently.
     */
    public function users(): UsersResource;

    /**
     * The ISteamUserStats endpoints, reached fluently.
     */
    public function stats(): StatsResource;

    /**
     * The ISteamApps endpoints, reached fluently.
     */
    public function apps(): AppsResource;

    /**
     * The ISteamNews endpoint, reached fluently.
     */
    public function news(): NewsResource;

    /**
     * Fetch player summaries for up to 100 Steam IDs.
     *
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerSummary>
     */
    public function summaries(array $steamIds): array;

    /**
     * Fetch ban records for up to 100 Steam IDs.
     *
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerBan>
     */
    public function bans(array $steamIds): array;

    /**
     * Fetch a player's friend list, optionally narrowed to one relationship.
     *
     * @return list<Friend>
     */
    public function friends(SteamId $steamId, ?FriendRelationship $relationship = null): array;

    /**
     * Fetch the community groups a player belongs to.
     *
     * @return list<UserGroup>
     */
    public function groups(SteamId $steamId): array;

    /**
     * Fetch the games owned by a player.
     *
     * @param  list<int>  $appIdsFilter
     * @return list<OwnedGame>
     */
    public function ownedGames(
        SteamId $steamId,
        array $appIdsFilter = [],
        bool $includeAppInfo = false,
        bool $includePlayedFreeGames = false,
    ): array;

    /**
     * Fetch the games a player played in the last two weeks.
     */
    public function recentlyPlayedGames(SteamId $steamId, ?int $count = null): RecentlyPlayedGames;

    /**
     * Fetch a player's Steam community level.
     */
    public function steamLevel(SteamId $steamId): int;

    /**
     * Fetch a player's badges, along with the level and XP they add up to.
     */
    public function badges(SteamId $steamId): PlayerBadges;

    /**
     * Fetch a player's progress through the community badge quests.
     *
     * @return list<CommunityBadgeQuest>
     */
    public function communityBadgeProgress(SteamId $steamId): array;

    /**
     * Fetch a player's stats for a single game.
     */
    public function userStats(SteamId $steamId, int $appId, ?Language $language = null): UserStats;

    /**
     * Fetch a player's achievements for a single game.
     */
    public function achievements(SteamId $steamId, int $appId, ?Language $language = null): PlayerAchievements;

    /**
     * Fetch how many players are in a game right now.
     */
    public function currentPlayers(int $appId): int;

    /**
     * Fetch how much of the player base has each achievement in a game.
     *
     * The argument keeps the name the base SDK gives it: Valve spells this one
     * endpoint's identifier `gameid`, though the value is an ordinary app ID.
     *
     * @return list<GlobalAchievement>
     */
    public function globalAchievements(int $gameId): array;

    /**
     * Fetch every stat and achievement a game publishes.
     *
     * An app publishing no schema answers with an empty {@see GameSchema} rather
     * than a failure; an app ID Steam does not know raises `AppNotFoundException`.
     */
    public function schema(int $appId, ?Language $language = null): GameSchema;

    /**
     * Resolve a Steam vanity URL slug to a {@see SteamId}.
     */
    public function resolveVanityUrl(string $vanityName): SteamId;

    /**
     * Check whether a game server running this version of an app is current.
     *
     * An app ID Steam does not know and an app running no versioned servers both
     * raise `AppVersionUnavailableException`.
     */
    public function upToDateCheck(int $appId, int $version): AppVersionCheck;

    /**
     * Fetch the game servers at an IP address, optionally narrowed to one query port.
     *
     * An address Steam rejects raises `InvalidServerAddressException`.
     *
     * @return list<GameServer>
     */
    public function serversAtAddress(string $address): array;

    /**
     * Fetch the Steam Datagram Relay network a game connects through, keyed by
     * point-of-presence code.
     *
     * An app ID Steam does not know raises `AppNotFoundException`.
     */
    public function sdrConfig(int $appId): SdrConfig;

    /**
     * Fetch an app's news, newest first.
     *
     * `count` caps how many items come back, while `total` on the result is what
     * Steam counted for the filter. An app ID Steam does not know, and some apps
     * it does, raise `AppNewsUnavailableException`.
     *
     * @param  list<string>  $feeds
     * @param  list<string>  $tags
     */
    public function appNews(
        int $appId,
        ?int $count = null,
        ?int $maxLength = null,
        ?DateTimeInterface $endDate = null,
        array $feeds = [],
        array $tags = [],
    ): AppNews;

    /**
     * Swap the connector's HTTP client for a Saloon mock, returning it for assertions.
     *
     * A faked retry keeps its tries but not the pause between them.
     *
     * @param  array<array-key, (callable(PendingRequest): mixed)|Fixture|MockResponse>  $responses
     *
     * @throws FakeOutsideTestsException when the application is not running tests
     */
    public function fake(array $responses = []): SteamFake;

    /**
     * Assert that a request matching the class name, URL pattern or closure was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSent(string|callable $value): void;

    /**
     * Assert that no request matching the class name, URL pattern or closure was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertNotSent(string|callable $request): void;

    /**
     * Assert that the fake recorded no requests at all.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertNothingSent(): void;

    /**
     * Assert how many requests were sent, optionally narrowed to one request class.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSentCount(int $count, ?string $requestClass = null): void;

    /**
     * Assert that the requests were sent in the given order, and nothing else with them.
     *
     * @param  array<Closure|class-string<Request>|string>  $callbacks
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSentInOrder(array $callbacks): void;

    /**
     * Every response the fake recorded, in the order they came back.
     *
     * @return array<Response>
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function recorded(): array;

    /**
     * The last request the fake handled, or null when nothing was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function lastRequest(): ?Request;

    /**
     * The last response the fake returned, or null when nothing was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function lastResponse(): ?Response;
}
