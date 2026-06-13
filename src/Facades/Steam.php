<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Facades;

use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Fkrzski\SteamApiSdk\SteamConnector connector()
 * @method static \Saloon\Http\Response send(\Saloon\Http\Request $request)
 * @method static \Saloon\Http\Pool pool(iterable<\Saloon\Http\Request>|callable $requests = [], int|callable $concurrency = 5, ?callable $responseHandler = null, ?callable $exceptionHandler = null)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\PlayerSummary> playerSummaries(list<\Fkrzski\SteamApiSdk\ValueObjects\SteamId> $steamIds)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\OwnedGame> ownedGames(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId, list<int> $appIdsFilter = [], bool $includeAppInfo = false, bool $includePlayedFreeGames = false)
 * @method static \Fkrzski\SteamApiSdk\Dto\UserStats userStatsForGame(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId, int $appId, ?string $language = null)
 * @method static \Fkrzski\SteamApiSdk\Dto\PlayerAchievements playerAchievements(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId, int $appId, ?string $language = null)
 * @method static \Fkrzski\SteamApiSdk\ValueObjects\SteamId resolveVanityUrl(string $vanityName)
 * @method static \Saloon\Http\Faking\MockClient fake(array<class-string, mixed> $responses = [])
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
