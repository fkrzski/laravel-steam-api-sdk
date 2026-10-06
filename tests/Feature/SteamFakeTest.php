<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppVersionCheckFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\CommunityBadgeQuestFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameSchemaFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameServerFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GlobalAchievementFactory;
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
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\PlayersFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\StatsFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\UsersFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;

mutates(SteamFake::class, UsersFake::class, PlayersFake::class, StatsFake::class, AppsFake::class);

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

it('chains from the array form', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(steamId()),
    ])->players()->steamLevel(42);

    expect(Steam::resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000')
        ->and(Steam::steamLevel(steamId()))->toBe(42);
});
