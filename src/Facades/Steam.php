<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Facades;

use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Support\Facades\Facade;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Pool;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * @method static SteamConnector connector()
 * @method static Response send(Request $request)
 * @method static Pool pool(iterable<Request>|callable $requests = [], int|callable $concurrency = 5, ?callable $responseHandler = null, ?callable $exceptionHandler = null)
 * @method static list<PlayerSummary> playerSummaries(list<SteamId> $steamIds)
 * @method static list<OwnedGame> ownedGames(SteamId $steamId, list<int> $appIdsFilter = [], bool $includeAppInfo = false, bool $includePlayedFreeGames = false)
 * @method static UserStats userStatsForGame(SteamId $steamId, int $appId, ?string $language = null)
 * @method static PlayerAchievements playerAchievements(SteamId $steamId, int $appId, ?string $language = null)
 * @method static SteamId resolveVanityUrl(string $vanityName)
 * @method static MockClient fake(array<class-string, mixed> $responses = [])
 *
 * @see SteamManager
 */
class Steam extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SteamManager::class;
    }
}
