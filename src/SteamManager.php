<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Closure;
use DateTimeInterface;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamManager as SteamManagerContract;
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
use Illuminate\Contracts\Foundation\Application;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Pool;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Laravel-friendly wrapper around the framework-agnostic {@see SteamConnector}.
 *
 * Resolves the connector through the container that built this manager, so a
 * long-lived worker never hands one request's connector to the next.
 */
final readonly class SteamManager implements SteamManagerContract
{
    /**
     * @param  Closure(): SteamConnector  $connectorResolver
     */
    public function __construct(
        private Closure $connectorResolver,
        private Application $app,
    ) {}

    /**
     * The underlying Saloon connector. Use this as an escape hatch for
     * advanced features (e.g. building custom requests) not exposed here.
     */
    public function connector(): SteamConnector
    {
        return ($this->connectorResolver)();
    }

    /**
     * Send a Steam request and return the raw Saloon response.
     */
    public function send(Request $request): Response
    {
        return $this->connector()->send($request);
    }

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
    ): Pool {
        return $this->connector()->pool($requests, $concurrency, $responseHandler, $exceptionHandler);
    }

    /**
     * The IPlayerService endpoints, reached fluently.
     *
     * Built by the connector rather than held here, so the resource always sends
     * through the connector this manager resolves right now — the one a fake sits on.
     */
    public function players(): PlayersResource
    {
        return $this->connector()->players();
    }

    /**
     * The ISteamUser endpoints, reached fluently.
     */
    public function users(): UsersResource
    {
        return $this->connector()->users();
    }

    /**
     * The ISteamUserStats endpoints, reached fluently.
     */
    public function stats(): StatsResource
    {
        return $this->connector()->stats();
    }

    /**
     * The ISteamApps endpoints, reached fluently.
     */
    public function apps(): AppsResource
    {
        return $this->connector()->apps();
    }

    /**
     * The ISteamNews endpoint, reached fluently.
     */
    public function news(): NewsResource
    {
        return $this->connector()->news();
    }

    /**
     * Fetch player summaries for up to 100 Steam IDs.
     *
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerSummary>
     */
    public function summaries(array $steamIds): array
    {
        return $this->users()->summaries($steamIds);
    }

    /**
     * Fetch ban records for up to 100 Steam IDs.
     *
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerBan>
     */
    public function bans(array $steamIds): array
    {
        return $this->users()->bans($steamIds);
    }

    /**
     * Fetch a player's friend list, optionally narrowed to one relationship.
     *
     * @return list<Friend>
     */
    public function friends(SteamId $steamId, ?FriendRelationship $relationship = null): array
    {
        return $this->users()->friends($steamId, $relationship);
    }

    /**
     * Fetch the community groups a player belongs to.
     *
     * @return list<UserGroup>
     */
    public function groups(SteamId $steamId): array
    {
        return $this->users()->groups($steamId);
    }

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
    ): array {
        return $this->players()->ownedGames(
            $steamId,
            $appIdsFilter,
            $includeAppInfo,
            $includePlayedFreeGames,
        );
    }

    /**
     * Fetch the games a player played in the last two weeks.
     *
     * `count` caps how many games come back, not what Steam counted.
     */
    public function recentlyPlayedGames(SteamId $steamId, ?int $count = null): RecentlyPlayedGames
    {
        return $this->players()->recentlyPlayedGames($steamId, $count);
    }

    /**
     * Fetch a player's Steam community level.
     */
    public function steamLevel(SteamId $steamId): int
    {
        return $this->players()->steamLevel($steamId);
    }

    /**
     * Fetch a player's badges, along with the level and XP they add up to.
     */
    public function badges(SteamId $steamId): PlayerBadges
    {
        return $this->players()->badges($steamId);
    }

    /**
     * Fetch a player's progress through the community badge quests.
     *
     * @return list<CommunityBadgeQuest>
     */
    public function communityBadgeProgress(SteamId $steamId): array
    {
        return $this->players()->communityBadgeProgress($steamId);
    }

    /**
     * Fetch a player's stats for a single game.
     */
    public function userStats(SteamId $steamId, int $appId, ?Language $language = null): UserStats
    {
        return $this->stats()->userStats($steamId, $appId, $language);
    }

    /**
     * Fetch a player's achievements for a single game.
     */
    public function achievements(SteamId $steamId, int $appId, ?Language $language = null): PlayerAchievements
    {
        return $this->stats()->achievements($steamId, $appId, $language);
    }

    /**
     * Fetch how many players are in a game right now.
     */
    public function currentPlayers(int $appId): int
    {
        return $this->stats()->currentPlayers($appId);
    }

    /**
     * Fetch how much of the player base has each achievement in a game.
     *
     * The argument keeps the name the base SDK gives it: Valve spells this one
     * endpoint's identifier `gameid`, though the value is an ordinary app ID.
     *
     * @return list<GlobalAchievement>
     */
    public function globalAchievements(int $gameId): array
    {
        return $this->stats()->globalAchievements($gameId);
    }

    /**
     * Fetch every stat and achievement a game publishes.
     *
     * An app publishing no schema answers with an empty {@see GameSchema} rather
     * than a failure; an app ID Steam does not know raises `AppNotFoundException`.
     */
    public function schema(int $appId, ?Language $language = null): GameSchema
    {
        return $this->stats()->schema($appId, $language);
    }

    /**
     * Resolve a Steam vanity URL slug to a {@see SteamId}.
     */
    public function resolveVanityUrl(string $vanityName): SteamId
    {
        return $this->users()->resolveVanityUrl($vanityName);
    }

    /**
     * Check whether a game server running this version of an app is current.
     *
     * An app ID Steam does not know and an app running no versioned servers both
     * raise `AppVersionUnavailableException`.
     */
    public function upToDateCheck(int $appId, int $version): AppVersionCheck
    {
        return $this->apps()->upToDateCheck($appId, $version);
    }

    /**
     * Fetch the game servers at an IP address, optionally narrowed to one query port.
     *
     * An address Steam rejects raises `InvalidServerAddressException`.
     *
     * @return list<GameServer>
     */
    public function serversAtAddress(string $address): array
    {
        return $this->apps()->serversAtAddress($address);
    }

    /**
     * Fetch the Steam Datagram Relay network a game connects through, keyed by
     * point-of-presence code.
     *
     * An app ID Steam does not know raises `AppNotFoundException`.
     */
    public function sdrConfig(int $appId): SdrConfig
    {
        return $this->apps()->sdrConfig($appId);
    }

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
    ): AppNews {
        return $this->news()->appNews($appId, $count, $maxLength, $endDate, $feeds, $tags);
    }

    /**
     * Swap the connector's HTTP client for a Saloon mock, returning it for assertions.
     *
     * Nothing detaches the mock, so faking is refused outside tests. A faked
     * retry keeps its tries but not the pause between them.
     *
     * @param  array<array-key, (callable(PendingRequest): mixed)|Fixture|MockResponse>  $responses
     *
     * @throws FakeOutsideTestsException when the application is not running tests
     */
    public function fake(array $responses = []): SteamFake
    {
        if (! $this->app->runningUnitTests()) {
            throw new FakeOutsideTestsException;
        }

        $mockClient = new SteamFake($responses);

        $connector = $this->connector();
        $connector->withMockClient($mockClient);

        // Saloon pauses with a bare usleep(), which Sleep::fake() cannot reach.
        $connector->retryInterval = 0;

        return $mockClient;
    }

    /**
     * Assert that a request matching the class name, URL pattern or closure was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSent(string|callable $value): void
    {
        $this->mockClient()->assertSent($value);
    }

    /**
     * Assert that no request matching the class name, URL pattern or closure was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertNotSent(string|callable $request): void
    {
        $this->mockClient()->assertNotSent($request);
    }

    /**
     * Assert that the fake recorded no requests at all.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertNothingSent(): void
    {
        $this->mockClient()->assertNothingSent();
    }

    /**
     * Assert how many requests were sent, optionally narrowed to one request class.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSentCount(int $count, ?string $requestClass = null): void
    {
        $this->mockClient()->assertSentCount($count, $requestClass);
    }

    /**
     * Assert that the requests were sent in the given order, and nothing else with them.
     *
     * @param  array<Closure|class-string<Request>|string>  $callbacks
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSentInOrder(array $callbacks): void
    {
        $this->mockClient()->assertSentInOrder($callbacks);
    }

    /**
     * Every response the fake recorded, in the order they came back.
     *
     * @return array<Response>
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function recorded(): array
    {
        return $this->mockClient()->getRecordedResponses();
    }

    /**
     * The last request the fake handled, or null when nothing was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function lastRequest(): ?Request
    {
        return $this->mockClient()->getLastRequest();
    }

    /**
     * The last response the fake returned, or null when nothing was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function lastResponse(): ?Response
    {
        return $this->mockClient()->getLastResponse();
    }

    /**
     * The mock attached by {@see self::fake()}, which the proxies above read from.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    private function mockClient(): MockClient
    {
        $mockClient = $this->connector()->getMockClient();

        if (! $mockClient instanceof MockClient) {
            throw new FakeNotInstalledException;
        }

        return $mockClient;
    }
}
