<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Facades;

use Fkrzski\LaravelSteamApiSdk\Contracts\SteamManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Fkrzski\SteamApiSdk\SteamConnector connector()
 * @method static \Saloon\Http\Response send(\Saloon\Http\Request $request)
 * @method static \Saloon\Http\Pool pool(iterable<\Saloon\Http\Request>|callable $requests = [], int|callable $concurrency = 5, ?callable $responseHandler = null, ?callable $exceptionHandler = null)
 * @method static \Fkrzski\SteamApiSdk\Http\Resources\PlayersResource players()
 * @method static \Fkrzski\SteamApiSdk\Http\Resources\UsersResource users()
 * @method static \Fkrzski\SteamApiSdk\Http\Resources\StatsResource stats()
 * @method static \Fkrzski\SteamApiSdk\Http\Resources\AppsResource apps()
 * @method static \Fkrzski\SteamApiSdk\Http\Resources\NewsResource news()
 * @method static list<\Fkrzski\SteamApiSdk\Dto\PlayerSummary> summaries(list<\Fkrzski\SteamApiSdk\ValueObjects\SteamId> $steamIds)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\PlayerBan> bans(list<\Fkrzski\SteamApiSdk\ValueObjects\SteamId> $steamIds)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\Friend> friends(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId, ?\Fkrzski\SteamApiSdk\Enums\FriendRelationship $relationship = null)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\UserGroup> groups(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\OwnedGame> ownedGames(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId, list<int> $appIdsFilter = [], bool $includeAppInfo = false, bool $includePlayedFreeGames = false)
 * @method static \Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames recentlyPlayedGames(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId, ?int $count = null)
 * @method static int steamLevel(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId)
 * @method static \Fkrzski\SteamApiSdk\Dto\PlayerBadges badges(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest> communityBadgeProgress(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId)
 * @method static \Fkrzski\SteamApiSdk\Dto\UserStats userStats(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId, int $appId, ?\Fkrzski\SteamApiSdk\Enums\Language $language = null)
 * @method static \Fkrzski\SteamApiSdk\Dto\PlayerAchievements achievements(\Fkrzski\SteamApiSdk\ValueObjects\SteamId $steamId, int $appId, ?\Fkrzski\SteamApiSdk\Enums\Language $language = null)
 * @method static int currentPlayers(int $appId)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\GlobalAchievement> globalAchievements(int $gameId)
 * @method static \Fkrzski\SteamApiSdk\Dto\GameSchema schema(int $appId, ?\Fkrzski\SteamApiSdk\Enums\Language $language = null)
 * @method static \Fkrzski\SteamApiSdk\ValueObjects\SteamId resolveVanityUrl(string $vanityName)
 * @method static \Fkrzski\SteamApiSdk\Dto\AppVersionCheck upToDateCheck(int $appId, int $version)
 * @method static list<\Fkrzski\SteamApiSdk\Dto\GameServer> serversAtAddress(string $address)
 * @method static \Fkrzski\SteamApiSdk\Dto\SdrConfig sdrConfig(int $appId)
 * @method static \Fkrzski\SteamApiSdk\Dto\AppNews appNews(int $appId, ?int $count = null, ?int $maxLength = null, ?\DateTimeInterface $endDate = null, list<string> $feeds = [], list<string> $tags = [])
 * @method static \Illuminate\Support\LazyCollection<int, \Fkrzski\SteamApiSdk\Dto\NewsItem> newsFeed(int $appId, int $perPage = 20, ?int $maxLength = null, ?\DateTimeInterface $endDate = null, list<string> $feeds = [], list<string> $tags = [])
 * @method static \Fkrzski\LaravelSteamApiSdk\Testing\SteamFake fake(array<array-key, (callable(\Saloon\Http\PendingRequest): mixed)|\Saloon\Http\Faking\Fixture|\Saloon\Http\Faking\MockResponse> $responses = [])
 * @method static void assertSent(string|callable $value)
 * @method static void assertNotSent(string|callable $request)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count, ?string $requestClass = null)
 * @method static void assertSentInOrder(array<\Closure|class-string<\Saloon\Http\Request>|string> $callbacks)
 * @method static array<\Saloon\Http\Response> recorded()
 * @method static \Saloon\Http\Request|null lastRequest()
 * @method static \Saloon\Http\Response|null lastResponse()
 *
 * @see SteamManager
 */
final class Steam extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SteamManager::class;
    }
}
