<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppNewsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppVersionCheckFactory;
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
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\AppsFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\NewsFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\PlayersFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\StatsFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\UsersFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Dto\UserStats;
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
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

mutates(SteamFake::class, UsersFake::class, PlayersFake::class, StatsFake::class, AppsFake::class, NewsFake::class);

it('attaches the steam fake it returns', function (): void {
    $fake = Steam::fake();

    expect(Steam::connector()->getMockClient())->toBe($fake);
});

it('hands the same mock back from every endpoint', function (Closure $fakeEndpoint): void {
    $fake = Steam::fake();

    expect($fakeEndpoint($fake))->toBe($fake);
})->with([
    'summaries' => [fn (SteamFake $fake): SteamFake => $fake->users()->summaries()],
    'bans' => [fn (SteamFake $fake): SteamFake => $fake->users()->bans()],
    'friends' => [fn (SteamFake $fake): SteamFake => $fake->users()->friends()],
    'groups' => [fn (SteamFake $fake): SteamFake => $fake->users()->groups()],
    'resolve vanity url' => [fn (SteamFake $fake): SteamFake => $fake->users()->resolveVanityUrl(steamId())],
    'owned games' => [fn (SteamFake $fake): SteamFake => $fake->players()->ownedGames()],
    'recently played games' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->recentlyPlayedGames(RecentlyPlayedGamesFactory::new()),
    ],
    'steam level' => [fn (SteamFake $fake): SteamFake => $fake->players()->steamLevel(42)],
    'badges' => [fn (SteamFake $fake): SteamFake => $fake->players()->badges(PlayerBadgesFactory::new())],
    'community badge progress' => [fn (SteamFake $fake): SteamFake => $fake->players()->communityBadgeProgress()],
    'user stats' => [fn (SteamFake $fake): SteamFake => $fake->stats()->userStats(UserStatsFactory::new())],
    'achievements' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->achievements(PlayerAchievementsFactory::new()),
    ],
    'current players' => [fn (SteamFake $fake): SteamFake => $fake->stats()->currentPlayers(12_345)],
    'global achievements' => [fn (SteamFake $fake): SteamFake => $fake->stats()->globalAchievements()],
    'schema' => [fn (SteamFake $fake): SteamFake => $fake->stats()->schema(GameSchemaFactory::new())],
    'up to date check' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->upToDateCheck(AppVersionCheckFactory::new()),
    ],
    'servers at address' => [fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddress()],
    'sdr config' => [fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfig(SdrConfigFactory::new())],
    'app news' => [fn (SteamFake $fake): SteamFake => $fake->news()->appNews(AppNewsFactory::new())],
    'news feed' => [fn (SteamFake $fake): SteamFake => $fake->news()->newsFeed(NewsItemFactory::new())],
]);

it('fakes every users endpoint through the chain', function (): void {
    Steam::fake()
        ->users()->summaries(PlayerSummaryFactory::new(), PlayerSummaryFactory::new()->personaName('Gabe'))
        ->users()->bans(PlayerBanFactory::new(), PlayerBanFactory::new()->vacBanned(2))
        ->users()->friends(FriendFactory::new(), FriendFactory::new())
        ->users()->groups(UserGroupFactory::new(), UserGroupFactory::new()->gid('103582791429521413'))
        ->users()->resolveVanityUrl(steamId());

    $summaries = Steam::summaries([steamId()]);
    $bans = Steam::bans([steamId()]);
    $groups = Steam::groups(steamId());

    expect($summaries)->toHaveCount(2)
        ->and($summaries[1]->personaName)->toBe('Gabe')
        ->and($bans)->toHaveCount(2)
        ->and($bans[1]->numberOfVacBans)->toBe(2)
        ->and(Steam::friends(steamId()))->toHaveCount(2)
        ->and($groups)->toHaveCount(2)
        ->and($groups[1]->gid)->toBe('103582791429521413')
        ->and(Steam::resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000');

    Steam::assertSentCount(5);
});

it('fakes every players endpoint through the chain', function (): void {
    Steam::fake()
        ->players()->ownedGames(OwnedGameFactory::new(), OwnedGameFactory::new()->withAppInfo())
        ->players()->recentlyPlayedGames(RecentlyPlayedGamesFactory::new()->games(
            RecentlyPlayedGameFactory::new()->name('Counter-Strike 2'),
        ))
        ->players()->steamLevel(42)
        ->players()->badges(PlayerBadgesFactory::new())
        ->players()->communityBadgeProgress(
            CommunityBadgeQuestFactory::new(),
            CommunityBadgeQuestFactory::new()->questId(202),
        );

    $ownedGames = Steam::ownedGames(steamId());
    $quests = Steam::communityBadgeProgress(steamId());

    expect($ownedGames)->toHaveCount(2)
        ->and($ownedGames[1]->name)->toBe('Dead by Daylight')
        ->and(Steam::recentlyPlayedGames(steamId())->games[0]->name)->toBe('Counter-Strike 2')
        ->and(Steam::steamLevel(steamId()))->toBe(42)
        ->and(Steam::badges(steamId())->playerLevel)->toBe(12)
        ->and($quests)->toHaveCount(2)
        ->and($quests[1]->questId)->toBe(202);

    Steam::assertSentCount(5);
});

it('fakes every stats endpoint through the chain', function (): void {
    Steam::fake()
        ->stats()->userStats(UserStatsFactory::new()->gameName('Dead by Daylight'))
        ->stats()->achievements(PlayerAchievementsFactory::new()->achievements(
            PlayerAchievementFactory::new(),
            PlayerAchievementFactory::new()->locked(),
        ))
        ->stats()->currentPlayers(12_345)
        ->stats()->globalAchievements(
            GlobalAchievementFactory::new(),
            GlobalAchievementFactory::new()->apiName('ACH_ESCAPE'),
        )
        ->stats()->schema(GameSchemaFactory::new()->gameName('Portal 2'));

    $globalAchievements = Steam::globalAchievements(gameId: 381210);

    expect(Steam::userStats(steamId(), appId: 381210)->gameName)->toBe('Dead by Daylight')
        ->and(Steam::achievements(steamId(), appId: 381210)->achievements)->toHaveCount(2)
        ->and(Steam::currentPlayers(appId: 381210))->toBe(12_345)
        ->and($globalAchievements)->toHaveCount(2)
        ->and($globalAchievements[1]->apiName)->toBe('ACH_ESCAPE')
        ->and(Steam::schema(appId: 620)->gameName)->toBe('Portal 2');

    Steam::assertSentCount(5);
});

it('fakes every apps endpoint through the chain', function (): void {
    Steam::fake()
        ->apps()->upToDateCheck(AppVersionCheckFactory::new()->outOfDate())
        ->apps()->serversAtAddress(GameServerFactory::new(), GameServerFactory::new()->withoutSpectatorPort())
        ->apps()->sdrConfig(SdrConfigFactory::new()->revision(42));

    $servers = Steam::serversAtAddress('108.181.62.21');

    expect(Steam::upToDateCheck(appId: 440, version: 1)->isUpToDate)->toBeFalse()
        ->and($servers)->toHaveCount(2)
        ->and($servers[1]->spectatorPort)->toBeNull()
        ->and(Steam::sdrConfig(appId: 730)->revision)->toBe(42);

    Steam::assertSentCount(3);
});

it('fakes the news endpoint through the chain', function (): void {
    Steam::fake()
        ->news()->appNews(AppNewsFactory::new()->items(
            NewsItemFactory::new(),
            NewsItemFactory::new()->communityAnnouncement(),
        )->total(3939));

    $news = Steam::appNews(appId: 440, count: 2);

    expect($news->total)->toBe(3939)
        ->and($news->items)->toHaveCount(2)
        ->and($news->items[1]->isCommunityAnnouncement)->toBeTrue();

    Steam::assertSentCount(1);
});

it('fakes a whole news feed through the chain', function (): void {
    Steam::fake()->news()->newsFeed(...newsFeedItems(3));

    expect(Steam::newsFeed(appId: 440, perPage: 2)->pluck('id')->all())->toBe(['1', '2', '3']);

    Steam::assertSentCount(2);
});

it('chains from the array form', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(steamId()),
    ])->players()->steamLevel(42);

    expect(Steam::resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000')
        ->and(Steam::steamLevel(steamId()))->toBe(42);
});

it('raises what the base sdk raises for a named failure', function (
    Closure $fail,
    Closure $call,
    string $exception,
    ?string $message = null,
): void {
    $fake = Steam::fake();

    expect($fail($fake))->toBe($fake)
        ->and($call)->toThrow($exception, $message);
})->with([
    'friends not public' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->friendsNotPublic(),
        fn (): array => Steam::friends(steamId()),
        ProfileNotPublicException::class,
    ],
    'groups not public' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->groupsNotPublic(),
        fn (): array => Steam::groups(steamId()),
        ProfileNotPublicException::class,
    ],
    'vanity url not found' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->vanityUrlNotFound(),
        fn (): SteamId => Steam::resolveVanityUrl('nobody'),
        SteamUserNotFoundException::class,
    ],
    'owned games not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->ownedGamesNotPublic(),
        fn (): array => Steam::ownedGames(steamId()),
        ProfileNotPublicException::class,
    ],
    'recently played games not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->recentlyPlayedGamesNotPublic(),
        fn (): RecentlyPlayedGames => Steam::recentlyPlayedGames(steamId()),
        ProfileNotPublicException::class,
    ],
    'steam level not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->steamLevelNotPublic(),
        fn (): int => Steam::steamLevel(steamId()),
        ProfileNotPublicException::class,
    ],
    'badges not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->badgesNotPublic(),
        fn (): PlayerBadges => Steam::badges(steamId()),
        ProfileNotPublicException::class,
    ],
    'community badge progress not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->communityBadgeProgressNotPublic(),
        fn (): array => Steam::communityBadgeProgress(steamId()),
        ProfileNotPublicException::class,
    ],
    'user stats not public' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->userStatsNotPublic(),
        fn (): UserStats => Steam::userStats(steamId(), appId: 381210),
        ProfileNotPublicException::class,
    ],
    'achievements unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->achievementsUnavailable(),
        fn (): PlayerAchievements => Steam::achievements(steamId(), appId: 381210),
        StatsUnavailableException::class,
    ],
    'current players app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->currentPlayersAppNotFound(),
        fn (): int => Steam::currentPlayers(appId: 1),
        AppNotFoundException::class,
    ],
    'global achievements unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->globalAchievementsUnavailable(),
        fn (): array => Steam::globalAchievements(gameId: 381210),
        StatsUnavailableException::class,
    ],
    'schema app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->schemaAppNotFound(),
        fn (): GameSchema => Steam::schema(appId: 1),
        AppNotFoundException::class,
    ],
    'up to date check unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->upToDateCheckUnavailable(),
        fn (): AppVersionCheck => Steam::upToDateCheck(appId: 999999999, version: 1),
        AppVersionUnavailableException::class,
    ],
    'servers at address invalid' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddressInvalid(),
        fn (): array => Steam::serversAtAddress('not-an-ip'),
        InvalidServerAddressException::class,
    ],
    // InvalidServerAddressException is a SteamApiException too, so the message tells them apart.
    'servers at address refused' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddressRefused(),
        fn (): array => Steam::serversAtAddress('127.0.0.1'),
        SteamApiException::class,
        'once per minute for a given IP',
    ],
    'sdr config app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfigAppNotFound(),
        fn (): SdrConfig => Steam::sdrConfig(appId: 999999999),
        AppNotFoundException::class,
    ],
    'app news unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->news()->appNewsUnavailable(),
        fn (): AppNews => Steam::appNews(appId: 480),
        AppNewsUnavailableException::class,
    ],
]);

it('fails every request without its own response', function (Closure $fail, string $exception): void {
    $fake = Steam::fake()->players()->steamLevel(42);

    expect($fail($fake))->toBe($fake)
        ->and(Steam::steamLevel(steamId()))->toBe(42)
        ->and(fn (): array => Steam::summaries([steamId()]))->toThrow($exception);
})->with([
    'invalid api key' => [
        fn (SteamFake $fake): SteamFake => $fake->invalidApiKey(),
        InvalidApiKeyException::class,
    ],
    'connection failed' => [
        fn (SteamFake $fake): SteamFake => $fake->connectionFailed(),
        SteamConnectionException::class,
    ],
]);
