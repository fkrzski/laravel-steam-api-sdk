<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Closure;
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
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Pool;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Laravel-friendly wrapper around the framework-agnostic {@see SteamConnector}.
 *
 * Resolves the connector lazily so the manager stays safe to register as a
 * singleton under Octane.
 */
class SteamManager
{
    /**
     * @param  Closure(): SteamConnector  $connectorResolver
     */
    public function __construct(
        protected readonly Closure $connectorResolver,
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
     * Fetch player summaries for up to 100 Steam IDs.
     *
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerSummary>
     */
    public function playerSummaries(array $steamIds): array
    {
        $request = new GetPlayerSummariesRequest($steamIds);

        return $request->createDtoFromResponse($this->send($request));
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
        $request = new GetOwnedGamesRequest(
            $steamId,
            $appIdsFilter,
            $includeAppInfo,
            $includePlayedFreeGames,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * Fetch a player's stats for a single game.
     */
    public function userStatsForGame(SteamId $steamId, int $appId, ?string $language = null): UserStats
    {
        $request = new GetUserStatsForGameRequest($steamId, $appId, $language);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * Fetch a player's achievements for a single game.
     */
    public function playerAchievements(SteamId $steamId, int $appId, ?string $language = null): PlayerAchievements
    {
        $request = new GetPlayerAchievementsRequest($steamId, $appId, $language);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * Resolve a Steam vanity URL slug to a {@see SteamId}.
     */
    public function resolveVanityUrl(string $vanityName): SteamId
    {
        $request = new ResolveVanityUrlRequest($vanityName);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * Swap the connector's HTTP client for a Saloon mock, returning it for assertions.
     *
     * @param  array<array-key, (callable(): mixed)|Fixture|MockResponse>  $responses
     */
    public function fake(array $responses = []): MockClient
    {
        $mockClient = new MockClient($responses);

        $this->connector()->withMockClient($mockClient);

        return $mockClient;
    }
}
