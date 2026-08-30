<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Closure;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamManager as SteamManagerContract;
use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeNotInstalledException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeOutsideTestsException;
use Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest;
use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Contracts\Foundation\Application;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
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
    public function userStats(SteamId $steamId, int $appId, ?string $language = null): UserStats
    {
        return $this->stats()->userStats($steamId, $appId, $language);
    }

    /**
     * Fetch a player's achievements for a single game.
     */
    public function achievements(SteamId $steamId, int $appId, ?string $language = null): PlayerAchievements
    {
        return $this->stats()->achievements($steamId, $appId, $language);
    }

    /**
     * Resolve a Steam vanity URL slug to a {@see SteamId}.
     */
    public function resolveVanityUrl(string $vanityName): SteamId
    {
        return $this->users()->resolveVanityUrl($vanityName);
    }

    /**
     * Swap the connector's HTTP client for a Saloon mock, returning it for assertions.
     *
     * Nothing detaches the mock, so faking is refused outside tests.
     *
     * @param  array<array-key, (callable(): mixed)|Fixture|MockResponse>  $responses
     *
     * @throws FakeOutsideTestsException when the application is not running tests
     */
    public function fake(array $responses = []): MockClient
    {
        if (! $this->app->runningUnitTests()) {
            throw new FakeOutsideTestsException;
        }

        $mockClient = new MockClient($responses);

        $this->connector()->withMockClient($mockClient);

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
