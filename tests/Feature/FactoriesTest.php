<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppNewsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppVersionCheckFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\BadgeFactory;
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
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SchemaAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SchemaStatFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SdrConfigFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SdrPointOfPresenceFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SdrRelayFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\SteamApiSdk\Enums\CommentPermission;
use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;
use Fkrzski\SteamApiSdk\Enums\EconomyBan;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Enums\PersonaState;
use Fkrzski\SteamApiSdk\Enums\ServerRegion;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

mutates(
    AppNewsFactory::class,
    AppVersionCheckFactory::class,
    BadgeFactory::class,
    CommunityBadgeQuestFactory::class,
    FriendFactory::class,
    GameSchemaFactory::class,
    GameServerFactory::class,
    GlobalAchievementFactory::class,
    NewsItemFactory::class,
    OwnedGameFactory::class,
    PlayerAchievementFactory::class,
    PlayerAchievementsFactory::class,
    PlayerBadgesFactory::class,
    PlayerBanFactory::class,
    PlayerSummaryFactory::class,
    RecentlyPlayedGameFactory::class,
    RecentlyPlayedGamesFactory::class,
    SchemaAchievementFactory::class,
    SchemaStatFactory::class,
    SdrConfigFactory::class,
    SdrPointOfPresenceFactory::class,
    SdrRelayFactory::class,
    UserGroupFactory::class,
    UserStatAchievementFactory::class,
    UserStatFactory::class,
    UserStatsFactory::class,
);

function otherSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000009');
}

// PlayerSummaryFactory

it('builds a public offline player summary payload', function (): void {
    expect(PlayerSummaryFactory::new()->toArray())->toBe([
        'steamid' => '76561198000000000',
        'personaname' => 'Gabe',
        'profileurl' => 'https://steamcommunity.com/id/gabelogannewell/',
        'avatar' => 'https://avatars.steamstatic.com/ee6b1c.jpg',
        'avatarmedium' => 'https://avatars.steamstatic.com/ee6b1c_medium.jpg',
        'avatarfull' => 'https://avatars.steamstatic.com/ee6b1c_full.jpg',
        'avatarhash' => 'ee6b1c0d1e3f2a4b5c6d7e8f9a0b1c2d3e4f5a6b',
        'communityvisibilitystate' => 3,
        'profilestate' => 1,
        'commentpermission' => 1,
        'personastate' => 0,
        'realname' => 'Gabe Newell',
        'primaryclanid' => '103582791429521412',
        'timecreated' => 1063407589,
        'lastlogoff' => 1600000000,
        'loccountrycode' => 'US',
        'locstatecode' => 'WA',
        'loccityid' => 3961,
    ]);
});

it('maps the default player summary payload onto the dto', function (): void {
    $summary = PlayerSummaryFactory::new()->make();

    expect($summary->steamId->value)->toBe('76561198000000000')
        ->and($summary->personaName)->toBe('Gabe')
        ->and($summary->communityVisibility)->toBe(CommunityVisibility::Visible)
        ->and($summary->commentPermission)->toBe(CommentPermission::Everyone)
        ->and($summary->personaState)->toBe(PersonaState::Offline)
        ->and($summary->hasCommunityProfile)->toBeTrue()
        ->and($summary->realName)->toBe('Gabe Newell')
        ->and($summary->timeCreated?->getTimestamp())->toBe(1063407589)
        ->and($summary->lastLogOff?->getTimestamp())->toBe(1600000000)
        ->and($summary->cityId)->toBe(3961)
        ->and($summary->gameId)->toBeNull();
});

it('drops the details steam withholds on a hidden profile', function (): void {
    $payload = PlayerSummaryFactory::new()->private()->toArray();

    expect($payload)->not->toHaveKeys(['realname', 'primaryclanid', 'timecreated', 'loccountrycode', 'locstatecode', 'loccityid'])
        ->and($payload['communityvisibilitystate'])->toBe(1);
});

it('maps a hidden profile onto the dto', function (): void {
    $summary = PlayerSummaryFactory::new()->private()->make();

    expect($summary->communityVisibility)->toBe(CommunityVisibility::Hidden)
        ->and($summary->realName)->toBeNull()
        ->and($summary->primaryClanId)->toBeNull()
        ->and($summary->timeCreated)->toBeNull()
        ->and($summary->countryCode)->toBeNull()
        ->and($summary->stateCode)->toBeNull()
        ->and($summary->cityId)->toBeNull();
});

it('marks a player summary as online', function (): void {
    expect(PlayerSummaryFactory::new()->online()->toArray()['personastate'])->toBe(1)
        ->and(PlayerSummaryFactory::new()->online()->make()->personaState)->toBe(PersonaState::Online);
});

it('puts a player summary in a game', function (): void {
    $summary = PlayerSummaryFactory::new()->inGame()->make();

    expect($summary->gameId)->toBe('381210')
        ->and($summary->gameExtraInfo)->toBe('Dead by Daylight');
});

it('puts a player summary in a named game', function (): void {
    $summary = PlayerSummaryFactory::new()->inGame('730', 'Counter-Strike 2')->make();

    expect($summary->gameId)->toBe('730')
        ->and($summary->gameExtraInfo)->toBe('Counter-Strike 2');
});

it('overrides the player summary steam id and persona name', function (): void {
    $summary = PlayerSummaryFactory::new()
        ->steamId(otherSteamId())
        ->personaName('Newell')
        ->make();

    expect($summary->steamId->value)->toBe('76561198000000009')
        ->and($summary->personaName)->toBe('Newell');
});

it('overrides arbitrary player summary keys through state', function (): void {
    $summary = PlayerSummaryFactory::new()->state(['profileurl' => 'https://example.test/'])->make();

    expect($summary->profileUrl)->toBe('https://example.test/');
});

// PlayerBanFactory

it('builds a clean player ban payload', function (): void {
    expect(PlayerBanFactory::new()->toArray())->toBe([
        'SteamId' => '76561198000000000',
        'CommunityBanned' => false,
        'VACBanned' => false,
        'NumberOfVACBans' => 0,
        'DaysSinceLastBan' => 0,
        'NumberOfGameBans' => 0,
        'EconomyBan' => 'none',
    ]);
});

it('maps a clean ban record onto the dto', function (): void {
    $ban = PlayerBanFactory::new()->make();

    expect($ban->steamId->value)->toBe('76561198000000000')
        ->and($ban->isCommunityBanned)->toBeFalse()
        ->and($ban->isVacBanned)->toBeFalse()
        ->and($ban->numberOfVacBans)->toBe(0)
        ->and($ban->numberOfGameBans)->toBe(0)
        ->and($ban->daysSinceLastBan)->toBe(0)
        ->and($ban->economyBan)->toBe(EconomyBan::None);
});

it('marks a ban record as vac banned', function (): void {
    $ban = PlayerBanFactory::new()->vacBanned()->make();

    expect($ban->isVacBanned)->toBeTrue()
        ->and($ban->numberOfVacBans)->toBe(1)
        ->and($ban->daysSinceLastBan)->toBe(30);
});

it('marks a ban record as vac banned a given number of times', function (): void {
    $ban = PlayerBanFactory::new()->vacBanned(3, 7)->make();

    expect($ban->numberOfVacBans)->toBe(3)
        ->and($ban->daysSinceLastBan)->toBe(7);
});

it('marks a ban record as game banned', function (): void {
    $ban = PlayerBanFactory::new()->gameBanned(2, 14)->make();

    expect($ban->numberOfGameBans)->toBe(2)
        ->and($ban->daysSinceLastBan)->toBe(14)
        ->and($ban->isVacBanned)->toBeFalse();
});

it('defaults a game ban to one ban thirty days ago', function (): void {
    $ban = PlayerBanFactory::new()->gameBanned()->make();

    expect($ban->numberOfGameBans)->toBe(1)
        ->and($ban->daysSinceLastBan)->toBe(30);
});

it('marks a ban record as community banned', function (): void {
    expect(PlayerBanFactory::new()->communityBanned()->make()->isCommunityBanned)->toBeTrue();
});

it('sets the economy ban on a ban record', function (): void {
    expect(PlayerBanFactory::new()->economyBan(EconomyBan::Probation)->make()->economyBan)
        ->toBe(EconomyBan::Probation);
});

it('overrides the ban record steam id', function (): void {
    expect(PlayerBanFactory::new()->steamId(otherSteamId())->make()->steamId->value)
        ->toBe('76561198000000009');
});

// FriendFactory

it('builds a friend payload', function (): void {
    expect(FriendFactory::new()->toArray())->toBe([
        'steamid' => '76561198000000001',
        'relationship' => 'friend',
        'friend_since' => 1600000000,
    ]);
});

it('maps a friend payload onto the dto', function (): void {
    $friend = FriendFactory::new()->make();

    expect($friend->steamId->value)->toBe('76561198000000001')
        ->and($friend->relationship)->toBe(FriendRelationship::Friend)
        ->and($friend->friendSince->getTimestamp())->toBe(1600000000);
});

it('narrows a friend to a relationship', function (): void {
    expect(FriendFactory::new()->relationship(FriendRelationship::All)->make()->relationship)
        ->toBe(FriendRelationship::All);
});

it('sets the date a friendship started', function (): void {
    $since = new DateTimeImmutable('@1700000000');

    expect(FriendFactory::new()->since($since)->make()->friendSince->getTimestamp())
        ->toBe(1700000000);
});

it('overrides the friend steam id', function (): void {
    expect(FriendFactory::new()->steamId(otherSteamId())->make()->steamId->value)
        ->toBe('76561198000000009');
});

// UserGroupFactory

it('builds a user group payload', function (): void {
    expect(UserGroupFactory::new()->toArray())->toBe([
        'gid' => '103582791429521412',
    ]);
});

it('maps a user group payload onto the dto', function (): void {
    expect(UserGroupFactory::new()->make()->gid)->toBe('103582791429521412');
});

it('overrides the group id', function (): void {
    expect(UserGroupFactory::new()->gid('103582791429521413')->make()->gid)
        ->toBe('103582791429521413');
});

it('keeps the keys a group state override leaves alone', function (): void {
    expect(UserGroupFactory::new()->state([])->toArray())->toBe([
        'gid' => '103582791429521412',
    ]);
});

// OwnedGameFactory

it('builds an owned game payload without app info', function (): void {
    expect(OwnedGameFactory::new()->toArray())->toBe([
        'appid' => 381210,
        'playtime_forever' => 1200,
        'playtime_2weeks' => 60,
    ]);
});

it('maps an owned game payload onto the dto', function (): void {
    $game = OwnedGameFactory::new()->make();

    expect($game->appId)->toBe(381210)
        ->and($game->playtimeForever)->toBe(1200)
        ->and($game->playtimeTwoWeeks)->toBe(60)
        ->and($game->name)->toBeNull()
        ->and($game->imgIconUrl)->toBeNull()
        ->and($game->hasCommunityVisibleStats)->toBeFalse();
});

it('adds app info to an owned game', function (): void {
    $game = OwnedGameFactory::new()->withAppInfo()->make();

    expect($game->name)->toBe('Dead by Daylight')
        ->and($game->imgIconUrl)->toBe('ee6b1c0d1e3f2a4b5c6d7e8f9a0b1c2d3e4f5a6b')
        ->and($game->hasCommunityVisibleStats)->toBeTrue();
});

it('adds named app info to an owned game', function (): void {
    expect(OwnedGameFactory::new()->withAppInfo('Counter-Strike 2')->make()->name)
        ->toBe('Counter-Strike 2');
});

it('drops recent playtime from a never played game', function (): void {
    $payload = OwnedGameFactory::new()->neverPlayed()->toArray();

    expect($payload)->toBe(['appid' => 381210, 'playtime_forever' => 0])
        ->and(OwnedGameFactory::new()->neverPlayed()->make()->playtimeTwoWeeks)->toBeNull();
});

it('sets recent playtime on an owned game', function (): void {
    expect(OwnedGameFactory::new()->playedRecently(240)->make()->playtimeTwoWeeks)->toBe(240);
});

it('overrides the owned game app id', function (): void {
    expect(OwnedGameFactory::new()->appId(730)->make()->appId)->toBe(730);
});

// RecentlyPlayedGameFactory

it('builds a recently played game payload', function (): void {
    expect(RecentlyPlayedGameFactory::new()->toArray())->toBe([
        'appid' => 381210,
        'name' => 'Dead by Daylight',
        'playtime_2weeks' => 60,
        'playtime_forever' => 1200,
        'img_icon_url' => 'ee6b1c0d1e3f2a4b5c6d7e8f9a0b1c2d3e4f5a6b',
        'playtime_windows_forever' => 900,
        'playtime_mac_forever' => 0,
        'playtime_linux_forever' => 100,
        'playtime_deck_forever' => 200,
    ]);
});

it('maps a recently played game payload onto the dto', function (): void {
    $game = RecentlyPlayedGameFactory::new()->make();

    expect($game->appId)->toBe(381210)
        ->and($game->name)->toBe('Dead by Daylight')
        ->and($game->playtimeTwoWeeks)->toBe(60)
        ->and($game->playtimeForever)->toBe(1200)
        ->and($game->imgIconUrl)->toBe('ee6b1c0d1e3f2a4b5c6d7e8f9a0b1c2d3e4f5a6b')
        ->and($game->playtimeWindowsForever)->toBe(900)
        ->and($game->playtimeMacForever)->toBe(0)
        ->and($game->playtimeLinuxForever)->toBe(100)
        ->and($game->playtimeDeckForever)->toBe(200);
});

it('overrides the recently played game app id and name', function (): void {
    $game = RecentlyPlayedGameFactory::new()->appId(730)->name('Counter-Strike 2')->make();

    expect($game->appId)->toBe(730)
        ->and($game->name)->toBe('Counter-Strike 2');
});

it('sets recent playtime on a recently played game', function (): void {
    expect(RecentlyPlayedGameFactory::new()->playedRecently(240)->make()->playtimeTwoWeeks)
        ->toBe(240);
});

// RecentlyPlayedGamesFactory

it('builds a recently played games payload around a single game', function (): void {
    expect(RecentlyPlayedGamesFactory::new()->toArray())->toBe([
        'total_count' => 1,
        'games' => [RecentlyPlayedGameFactory::new()->toArray()],
    ]);
});

it('maps a recently played games payload onto the dto', function (): void {
    $recent = RecentlyPlayedGamesFactory::new()->make();

    expect($recent->totalCount)->toBe(1)
        ->and($recent->games)->toHaveCount(1)
        ->and($recent->games[0]->appId)->toBe(381210);
});

it('counts the recently played games it replaces', function (): void {
    $recent = RecentlyPlayedGamesFactory::new()
        ->games(
            RecentlyPlayedGameFactory::new()->appId(381210),
            RecentlyPlayedGameFactory::new()->appId(730),
        )
        ->make();

    expect($recent->totalCount)->toBe(2)
        ->and($recent->games)->toHaveCount(2)
        ->and($recent->games[1]->appId)->toBe(730);
});

it('keeps the recently played total apart from the games listed', function (): void {
    $recent = RecentlyPlayedGamesFactory::new()
        ->games(RecentlyPlayedGameFactory::new())
        ->totalCount(40)
        ->make();

    expect($recent->totalCount)->toBe(40)
        ->and($recent->games)->toHaveCount(1);
});

it('drops the games key for a player who played nothing', function (): void {
    $payload = RecentlyPlayedGamesFactory::new()->nothingPlayed();

    expect($payload->toArray())->toBe(['total_count' => 0])
        ->and($payload->make()->games)->toBeEmpty();
});

// UserStatFactory

it('builds a user stat payload', function (): void {
    expect(UserStatFactory::new()->toArray())->toBe([
        'name' => 'DBD_KillerSkulls',
        'value' => 42,
    ]);
});

it('maps a user stat payload onto the dto', function (): void {
    $stat = UserStatFactory::new()->make();

    expect($stat->name)->toBe('DBD_KillerSkulls')
        ->and($stat->value)->toBe(42);
});

it('overrides the user stat name and value', function (): void {
    $stat = UserStatFactory::new()->name('DBD_SurvivorSkulls')->value(1.5)->make();

    expect($stat->name)->toBe('DBD_SurvivorSkulls')
        ->and($stat->value)->toBe(1.5);
});

// UserStatAchievementFactory

it('builds a user stat achievement payload', function (): void {
    expect(UserStatAchievementFactory::new()->toArray())->toBe([
        'name' => 'ACH_UNLOCK_KILLER_CHARACTER',
        'achieved' => 1,
    ]);
});

it('maps a user stat achievement payload onto the dto', function (): void {
    $achievement = UserStatAchievementFactory::new()->make();

    expect($achievement->name)->toBe('ACH_UNLOCK_KILLER_CHARACTER')
        ->and($achievement->achieved)->toBeTrue();
});

it('locks a user stat achievement', function (): void {
    expect(UserStatAchievementFactory::new()->locked()->toArray())->toBe([
        'name' => 'ACH_UNLOCK_KILLER_CHARACTER',
        'achieved' => 0,
    ])->and(UserStatAchievementFactory::new()->locked()->make()->achieved)->toBeFalse();
});

it('unlocks a user stat achievement', function (): void {
    expect(UserStatAchievementFactory::new()->locked()->achieved()->make()->achieved)->toBeTrue();
});

it('overrides the user stat achievement name', function (): void {
    expect(UserStatAchievementFactory::new()->name('ACH_ESCAPE')->make()->name)->toBe('ACH_ESCAPE');
});

// UserStatsFactory

it('builds a user stats payload', function (): void {
    expect(UserStatsFactory::new()->toArray())->toBe([
        'steamID' => '76561198000000000',
        'gameName' => 'Dead by Daylight',
        'stats' => [['name' => 'DBD_KillerSkulls', 'value' => 42]],
        'achievements' => [['name' => 'ACH_UNLOCK_KILLER_CHARACTER', 'achieved' => 1]],
    ]);
});

it('maps a user stats payload onto the dto', function (): void {
    $stats = UserStatsFactory::new()->make();

    expect($stats->steamId->value)->toBe('76561198000000000')
        ->and($stats->gameName)->toBe('Dead by Daylight')
        ->and($stats->stats)->toHaveCount(1)
        ->and($stats->stats[0]->name)->toBe('DBD_KillerSkulls')
        ->and($stats->achievements)->toHaveCount(1)
        ->and($stats->achievements[0]->name)->toBe('ACH_UNLOCK_KILLER_CHARACTER');
});

it('replaces the stats on a user stats payload', function (): void {
    $stats = UserStatsFactory::new()
        ->stats(
            UserStatFactory::new()->name('DBD_KillerSkulls')->value(1),
            UserStatFactory::new()->name('DBD_SurvivorSkulls')->value(2),
        )
        ->make();

    expect($stats->stats)->toHaveCount(2)
        ->and($stats->stats[1]->name)->toBe('DBD_SurvivorSkulls')
        ->and($stats->stats[1]->value)->toBe(2);
});

it('replaces the achievements on a user stats payload', function (): void {
    $stats = UserStatsFactory::new()
        ->achievements(UserStatAchievementFactory::new()->name('ACH_ESCAPE')->locked())
        ->make();

    expect($stats->achievements)->toHaveCount(1)
        ->and($stats->achievements[0]->name)->toBe('ACH_ESCAPE')
        ->and($stats->achievements[0]->achieved)->toBeFalse();
});

it('drops both collections from an empty user stats payload', function (): void {
    expect(UserStatsFactory::new()->empty()->toArray())->toBe([
        'steamID' => '76561198000000000',
        'gameName' => 'Dead by Daylight',
    ]);

    $stats = UserStatsFactory::new()->empty()->make();

    expect($stats->stats)->toBeEmpty()
        ->and($stats->achievements)->toBeEmpty();
});

it('overrides the user stats steam id and game name', function (): void {
    $stats = UserStatsFactory::new()->steamId(otherSteamId())->gameName('Counter-Strike 2')->make();

    expect($stats->steamId->value)->toBe('76561198000000009')
        ->and($stats->gameName)->toBe('Counter-Strike 2');
});

// PlayerAchievementFactory

it('builds an unlocked player achievement payload', function (): void {
    expect(PlayerAchievementFactory::new()->toArray())->toBe([
        'apiname' => 'ACH_UNLOCK_KILLER_CHARACTER',
        'achieved' => 1,
        'unlocktime' => 1600000000,
    ]);
});

it('maps a player achievement payload onto the dto', function (): void {
    $achievement = PlayerAchievementFactory::new()->make();

    expect($achievement->apiName)->toBe('ACH_UNLOCK_KILLER_CHARACTER')
        ->and($achievement->achieved)->toBeTrue()
        ->and($achievement->unlockedAt?->getTimestamp())->toBe(1600000000)
        ->and($achievement->name)->toBeNull()
        ->and($achievement->description)->toBeNull();
});

it('locks a player achievement', function (): void {
    expect(PlayerAchievementFactory::new()->locked()->toArray())->toBe([
        'apiname' => 'ACH_UNLOCK_KILLER_CHARACTER',
        'achieved' => 0,
        'unlocktime' => 0,
    ]);

    $achievement = PlayerAchievementFactory::new()->locked()->make();

    expect($achievement->achieved)->toBeFalse()
        ->and($achievement->unlockedAt)->toBeNull();
});

it('unlocks a player achievement at a given time', function (): void {
    $achievement = PlayerAchievementFactory::new()
        ->locked()
        ->unlocked(new DateTimeImmutable('@1700000000'))
        ->make();

    expect($achievement->achieved)->toBeTrue()
        ->and($achievement->unlockedAt?->getTimestamp())->toBe(1700000000);
});

it('adds localized details to a player achievement', function (): void {
    $achievement = PlayerAchievementFactory::new()->withDetails()->make();

    expect($achievement->name)->toBe('Left for Dead')
        ->and($achievement->description)->toBe('Unlock a killer character.');
});

it('adds custom details to a player achievement', function (): void {
    $achievement = PlayerAchievementFactory::new()->withDetails('Escape', 'Escape a trial.')->make();

    expect($achievement->name)->toBe('Escape')
        ->and($achievement->description)->toBe('Escape a trial.');
});

it('overrides the player achievement api name', function (): void {
    expect(PlayerAchievementFactory::new()->apiName('ACH_ESCAPE')->make()->apiName)->toBe('ACH_ESCAPE');
});

// PlayerAchievementsFactory

it('builds a player achievements payload', function (): void {
    expect(PlayerAchievementsFactory::new()->toArray())->toBe([
        'steamID' => '76561198000000000',
        'gameName' => 'Dead by Daylight',
        'success' => true,
        'achievements' => [[
            'apiname' => 'ACH_UNLOCK_KILLER_CHARACTER',
            'achieved' => 1,
            'unlocktime' => 1600000000,
        ]],
    ]);
});

it('maps a player achievements payload onto the dto', function (): void {
    $achievements = PlayerAchievementsFactory::new()->make();

    expect($achievements->steamId->value)->toBe('76561198000000000')
        ->and($achievements->gameName)->toBe('Dead by Daylight')
        ->and($achievements->achievements)->toHaveCount(1)
        ->and($achievements->achievements[0]->apiName)->toBe('ACH_UNLOCK_KILLER_CHARACTER');
});

it('replaces the achievements on a player achievements payload', function (): void {
    $achievements = PlayerAchievementsFactory::new()
        ->achievements(
            PlayerAchievementFactory::new()->apiName('ACH_ESCAPE')->locked(),
            PlayerAchievementFactory::new()->apiName('ACH_SURVIVE'),
        )
        ->make();

    expect($achievements->achievements)->toHaveCount(2)
        ->and($achievements->achievements[0]->apiName)->toBe('ACH_ESCAPE')
        ->and($achievements->achievements[0]->achieved)->toBeFalse()
        ->and($achievements->achievements[1]->achieved)->toBeTrue();
});

it('overrides the player achievements steam id and game name', function (): void {
    $achievements = PlayerAchievementsFactory::new()
        ->steamId(otherSteamId())
        ->gameName('Counter-Strike 2')
        ->make();

    expect($achievements->steamId->value)->toBe('76561198000000009')
        ->and($achievements->gameName)->toBe('Counter-Strike 2');
});

// BadgeFactory

it('builds a game badge payload', function (): void {
    expect(BadgeFactory::new()->toArray())->toBe([
        'badgeid' => 13,
        'appid' => 381210,
        'level' => 5,
        'completion_time' => 1600000000,
        'xp' => 500,
        'border_color' => 0,
        'scarcity' => 12345,
    ]);
});

it('maps a badge payload onto the dto', function (): void {
    $badge = BadgeFactory::new()->make();

    expect($badge->badgeId)->toBe(13)
        ->and($badge->appId)->toBe(381210)
        ->and($badge->level)->toBe(5)
        ->and($badge->completedAt->getTimestamp())->toBe(1600000000)
        ->and($badge->xp)->toBe(500)
        ->and($badge->borderColor)->toBe(0)
        ->and($badge->scarcity)->toBe(12345)
        ->and($badge->communityItemId)->toBeNull();
});

it('keeps the community item id a string', function (): void {
    $badge = BadgeFactory::new()->communityItem()->make();

    expect($badge->communityItemId)->toBe('2101234567890123456');
});

it('sets a custom community item id on a badge', function (): void {
    expect(BadgeFactory::new()->communityItem('2109876543210987654')->make()->communityItemId)
        ->toBe('2109876543210987654');
});

it('drops the app id and border colour from a badge belonging to no app', function (): void {
    expect(BadgeFactory::new()->withoutApp()->toArray())->toBe([
        'badgeid' => 13,
        'level' => 5,
        'completion_time' => 1600000000,
        'xp' => 500,
        'scarcity' => 12345,
    ]);

    $badge = BadgeFactory::new()->withoutApp()->make();

    expect($badge->appId)->toBeNull()
        ->and($badge->borderColor)->toBeNull();
});

it('sets the date a badge was completed', function (): void {
    $badge = BadgeFactory::new()->completedAt(new DateTimeImmutable('@1700000000'))->make();

    expect($badge->completedAt->getTimestamp())->toBe(1700000000);
});

it('overrides the badge id, app id and level', function (): void {
    $badge = BadgeFactory::new()->badgeId(2)->appId(730)->level(3)->make();

    expect($badge->badgeId)->toBe(2)
        ->and($badge->appId)->toBe(730)
        ->and($badge->level)->toBe(3);
});

it('overrides arbitrary badge keys through state', function (): void {
    expect(BadgeFactory::new()->state(['xp' => 750])->make()->xp)->toBe(750);
});

// PlayerBadgesFactory

it('builds a player badges payload', function (): void {
    expect(PlayerBadgesFactory::new()->toArray())->toBe([
        'badges' => [BadgeFactory::new()->toArray()],
        'player_xp' => 1500,
        'player_level' => 12,
        'player_xp_needed_to_level_up' => 100,
        'player_xp_needed_current_level' => 1400,
    ]);
});

it('maps a player badges payload onto the dto', function (): void {
    $badges = PlayerBadgesFactory::new()->make();

    expect($badges->badges)->toHaveCount(1)
        ->and($badges->badges[0]->badgeId)->toBe(13)
        ->and($badges->playerXp)->toBe(1500)
        ->and($badges->playerLevel)->toBe(12)
        ->and($badges->xpNeededToLevelUp)->toBe(100)
        ->and($badges->xpNeededForCurrentLevel)->toBe(1400);
});

it('replaces the badges on a player badges payload', function (): void {
    $badges = PlayerBadgesFactory::new()
        ->badges(
            BadgeFactory::new()->badgeId(1)->withoutApp(),
            BadgeFactory::new()->badgeId(2)->communityItem(),
        )
        ->make();

    expect($badges->badges)->toHaveCount(2)
        ->and($badges->badges[0]->appId)->toBeNull()
        ->and($badges->badges[1]->communityItemId)->toBe('2101234567890123456');
});

it('drops the badges key from an account that has earned none', function (): void {
    expect(PlayerBadgesFactory::new()->withoutBadges()->toArray())->toBe([
        'player_xp' => 1500,
        'player_level' => 12,
        'player_xp_needed_to_level_up' => 100,
        'player_xp_needed_current_level' => 1400,
    ])
        ->and(PlayerBadgesFactory::new()->withoutBadges()->make()->badges)->toBeEmpty();
});

it('overrides the player level', function (): void {
    expect(PlayerBadgesFactory::new()->level(42)->make()->playerLevel)->toBe(42);
});

it('overrides arbitrary player badges keys through state', function (): void {
    expect(PlayerBadgesFactory::new()->state(['player_xp' => 9000])->make()->playerXp)->toBe(9000);
});

// CommunityBadgeQuestFactory

it('builds a completed community badge quest payload', function (): void {
    expect(CommunityBadgeQuestFactory::new()->toArray())->toBe([
        'questid' => 115,
        'completed' => true,
    ]);
});

it('maps a community badge quest payload onto the dto', function (): void {
    $quest = CommunityBadgeQuestFactory::new()->make();

    expect($quest->questId)->toBe(115)
        ->and($quest->completed)->toBeTrue();
});

it('marks a community badge quest as incomplete', function (): void {
    expect(CommunityBadgeQuestFactory::new()->incomplete()->make()->completed)->toBeFalse();
});

it('overrides the quest id', function (): void {
    expect(CommunityBadgeQuestFactory::new()->questId(202)->make()->questId)->toBe(202);
});

// GlobalAchievementFactory

it('builds a global achievement payload', function (): void {
    expect(GlobalAchievementFactory::new()->toArray())->toBe([
        'name' => 'ACH_UNLOCK_KILLER_CHARACTER',
        'percent' => '32.4',
    ]);
});

it('maps a global achievement payload onto the dto', function (): void {
    $achievement = GlobalAchievementFactory::new()->make();

    expect($achievement->apiName)->toBe('ACH_UNLOCK_KILLER_CHARACTER')
        ->and($achievement->percent)->toBe(32.4);
});

it('keeps a global achievement percentage a string in the payload', function (): void {
    expect(GlobalAchievementFactory::new()->percent(12.5)->toArray()['percent'])->toBe('12.5');
});

it('overrides the global achievement api name and percentage', function (): void {
    $achievement = GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5)->make();

    expect($achievement->apiName)->toBe('ACH_ESCAPE')
        ->and($achievement->percent)->toBe(12.5);
});

// SchemaStatFactory

it('builds a schema stat payload', function (): void {
    expect(SchemaStatFactory::new()->toArray())->toBe([
        'name' => 'DBD_KillerSkulls',
        'defaultvalue' => 0,
        'displayName' => 'Killer Skulls',
    ]);
});

it('maps a schema stat payload onto the dto', function (): void {
    $stat = SchemaStatFactory::new()->make();

    expect($stat->apiName)->toBe('DBD_KillerSkulls')
        ->and($stat->name)->toBe('Killer Skulls')
        ->and($stat->defaultValue)->toBe(0);
});

it('overrides the schema stat api name, name and default value', function (): void {
    $stat = SchemaStatFactory::new()
        ->apiName('DBD_SurvivorSkulls')
        ->name('Survivor Skulls')
        ->defaultValue(1.5)
        ->make();

    expect($stat->apiName)->toBe('DBD_SurvivorSkulls')
        ->and($stat->name)->toBe('Survivor Skulls')
        ->and($stat->defaultValue)->toBe(1.5);
});

it('reads a blank stat display name as no name at all', function (): void {
    $stat = SchemaStatFactory::new()->unnamed();

    expect($stat->toArray()['displayName'])->toBe('')
        ->and($stat->make()->name)->toBeNull();
});

// SchemaAchievementFactory

it('builds a schema achievement payload', function (): void {
    expect(SchemaAchievementFactory::new()->toArray())->toBe([
        'name' => 'ACH_UNLOCK_KILLER_CHARACTER',
        'displayName' => 'Left for Dead',
        'hidden' => 0,
        'description' => 'Unlock a killer character.',
        'icon' => 'https://cdn.steamstatic.com/steamcommunity/public/images/apps/381210/ee6b1c.jpg',
        'icongray' => 'https://cdn.steamstatic.com/steamcommunity/public/images/apps/381210/ee6b1c_gray.jpg',
    ]);
});

it('maps a schema achievement payload onto the dto', function (): void {
    $achievement = SchemaAchievementFactory::new()->make();

    expect($achievement->apiName)->toBe('ACH_UNLOCK_KILLER_CHARACTER')
        ->and($achievement->name)->toBe('Left for Dead')
        ->and($achievement->description)->toBe('Unlock a killer character.')
        ->and($achievement->hidden)->toBeFalse()
        ->and($achievement->icon)->toBe('https://cdn.steamstatic.com/steamcommunity/public/images/apps/381210/ee6b1c.jpg')
        ->and($achievement->iconGray)->toBe('https://cdn.steamstatic.com/steamcommunity/public/images/apps/381210/ee6b1c_gray.jpg');
});

it('overrides the schema achievement api name, name and description', function (): void {
    $achievement = SchemaAchievementFactory::new()
        ->apiName('ACH_ESCAPE')
        ->name('Escape Artist')
        ->description('Escape through the hatch.')
        ->make();

    expect($achievement->apiName)->toBe('ACH_ESCAPE')
        ->and($achievement->name)->toBe('Escape Artist')
        ->and($achievement->description)->toBe('Escape through the hatch.');
});

it('overrides the schema achievement icons', function (): void {
    $achievement = SchemaAchievementFactory::new()
        ->icons('https://example.test/icon.jpg', 'https://example.test/icon_gray.jpg')
        ->make();

    expect($achievement->icon)->toBe('https://example.test/icon.jpg')
        ->and($achievement->iconGray)->toBe('https://example.test/icon_gray.jpg');
});

it('hides a schema achievement', function (): void {
    $achievement = SchemaAchievementFactory::new()->hidden();

    expect($achievement->toArray()['hidden'])->toBe(1)
        ->and($achievement->make()->hidden)->toBeTrue();
});

it('shows a hidden schema achievement again', function (): void {
    $achievement = SchemaAchievementFactory::new()->hidden()->visible();

    expect($achievement->toArray()['hidden'])->toBe(0)
        ->and($achievement->make()->hidden)->toBeFalse();
});

it('drops the description of a schema achievement', function (): void {
    $achievement = SchemaAchievementFactory::new()->withoutDescription();

    expect($achievement->toArray())->not->toHaveKey('description')
        ->and($achievement->make()->description)->toBeNull();
});

// GameSchemaFactory

it('builds a game schema payload', function (): void {
    expect(GameSchemaFactory::new()->toArray())->toBe([
        'gameName' => 'Dead by Daylight',
        'gameVersion' => '17',
        'availableGameStats' => [
            'stats' => [SchemaStatFactory::new()->toArray()],
            'achievements' => [SchemaAchievementFactory::new()->toArray()],
        ],
    ]);
});

it('maps a game schema payload onto the dto', function (): void {
    $schema = GameSchemaFactory::new()->make();

    expect($schema->gameName)->toBe('Dead by Daylight')
        ->and($schema->gameVersion)->toBe('17')
        ->and($schema->stats)->toHaveCount(1)
        ->and($schema->stats[0]->apiName)->toBe('DBD_KillerSkulls')
        ->and($schema->achievements)->toHaveCount(1)
        ->and($schema->achievements[0]->apiName)->toBe('ACH_UNLOCK_KILLER_CHARACTER');
});

it('overrides the game name and version', function (): void {
    $schema = GameSchemaFactory::new()->gameName('Portal 2')->gameVersion('20')->make();

    expect($schema->gameName)->toBe('Portal 2')
        ->and($schema->gameVersion)->toBe('20');
});

it('reads a blank game name as no name at all', function (): void {
    $schema = GameSchemaFactory::new()->unnamed();

    expect($schema->toArray()['gameName'])->toBe('')
        ->and($schema->make()->gameName)->toBeNull();
});

// Both lists sit under `availableGameStats`, so setting one has to leave the other
// standing.
it('replaces the schema stats and achievements side by side', function (): void {
    $schema = GameSchemaFactory::new()
        ->stats(
            SchemaStatFactory::new()->apiName('DBD_SurvivorSkulls'),
            SchemaStatFactory::new()->apiName('DBD_BloodwebPoints'),
        )
        ->achievements(SchemaAchievementFactory::new()->apiName('ACH_ESCAPE'))
        ->make();

    expect($schema->stats)->toHaveCount(2)
        ->and($schema->stats[0]->apiName)->toBe('DBD_SurvivorSkulls')
        ->and($schema->stats[1]->apiName)->toBe('DBD_BloodwebPoints')
        ->and($schema->achievements)->toHaveCount(1)
        ->and($schema->achievements[0]->apiName)->toBe('ACH_ESCAPE');
});

it('builds the schema an app publishing none answers with', function (): void {
    expect(GameSchemaFactory::new()->empty()->toArray())->toBeEmpty();
});

it('maps a published-nothing schema onto an empty dto', function (): void {
    $schema = GameSchemaFactory::new()->empty()->make();

    expect($schema->gameName)->toBeNull()
        ->and($schema->gameVersion)->toBeNull()
        ->and($schema->stats)->toBeEmpty()
        ->and($schema->achievements)->toBeEmpty();
});

// AppVersionCheckFactory

it('builds an up to date version check payload', function (): void {
    expect(AppVersionCheckFactory::new()->toArray())->toBe([
        'up_to_date' => true,
        'version_is_listable' => true,
    ]);
});

it('maps a version check payload onto the dto', function (): void {
    $check = AppVersionCheckFactory::new()->make();

    expect($check->isUpToDate)->toBeTrue()
        ->and($check->isListable)->toBeTrue()
        ->and($check->requiredVersion)->toBeNull()
        ->and($check->message)->toBeNull();
});

it('marks a version check as out of date', function (): void {
    expect(AppVersionCheckFactory::new()->outOfDate()->toArray())->toBe([
        'up_to_date' => false,
        'version_is_listable' => false,
        'required_version' => 10828683,
        'message' => 'Your server is out of date, please upgrade',
    ]);

    $check = AppVersionCheckFactory::new()->outOfDate()->make();

    expect($check->isUpToDate)->toBeFalse()
        ->and($check->isListable)->toBeFalse()
        ->and($check->requiredVersion)->toBe(10828683)
        ->and($check->message)->toBe('Your server is out of date, please upgrade');
});

it('sets the version and message an out of date check asks for', function (): void {
    $check = AppVersionCheckFactory::new()
        ->outOfDate(requiredVersion: 1418, message: 'Server version required: 1.41.8.6')
        ->make();

    expect($check->requiredVersion)->toBe(1418)
        ->and($check->message)->toBe('Server version required: 1.41.8.6');
});

it('overrides arbitrary version check keys through state', function (): void {
    $check = AppVersionCheckFactory::new()->outOfDate()->state(['version_is_listable' => true])->make();

    expect($check->isUpToDate)->toBeFalse()
        ->and($check->isListable)->toBeTrue();
});

// GameServerFactory

it('builds a game server payload', function (): void {
    expect(GameServerFactory::new()->toArray())->toBe([
        'addr' => '108.181.62.21:27015',
        'steamid' => '85568392924469984',
        'appid' => 440,
        'gamedir' => 'tf',
        'region' => 0,
        'secure' => true,
        'lan' => false,
        'gameport' => 27015,
        'specport' => 27016,
    ]);
});

it('maps a game server payload onto the dto', function (): void {
    $server = GameServerFactory::new()->make();

    expect($server->address)->toBe('108.181.62.21:27015')
        ->and($server->steamId->value)->toBe('85568392924469984')
        ->and($server->appId)->toBe(440)
        ->and($server->gameDir)->toBe('tf')
        ->and($server->region)->toBe(ServerRegion::UsEast)
        ->and($server->isSecure)->toBeTrue()
        ->and($server->isLan)->toBeFalse()
        ->and($server->gamePort)->toBe(27015)
        ->and($server->spectatorPort)->toBe(27016);
});

it('overrides the game server address, steam id, app id and region', function (): void {
    $server = GameServerFactory::new()
        ->address('216.39.241.176:28015')
        ->steamId(SteamId::fromSteamId64('90293757517545488'))
        ->appId(252490)
        ->region(ServerRegion::Europe)
        ->make();

    expect($server->address)->toBe('216.39.241.176:28015')
        ->and($server->steamId->value)->toBe('90293757517545488')
        ->and($server->appId)->toBe(252490)
        ->and($server->region)->toBe(ServerRegion::Europe);
});

it('marks a game server as insecure', function (): void {
    expect(GameServerFactory::new()->insecure()->make()->isSecure)->toBeFalse();
});

it('reads a zero spectator port as no port at all', function (): void {
    $server = GameServerFactory::new()->withoutSpectatorPort();

    expect($server->toArray()['specport'])->toBe(0)
        ->and($server->make()->spectatorPort)->toBeNull();
});

it('overrides arbitrary game server keys through state', function (): void {
    expect(GameServerFactory::new()->state(['gamedir' => 'rust'])->make()->gameDir)->toBe('rust');
});

// SdrRelayFactory

it('builds a relay payload', function (): void {
    expect(SdrRelayFactory::new()->toArray())->toBe([
        'ipv4' => '155.133.248.36',
        'port_range' => [27015, 27060],
    ]);
});

it('maps a relay payload onto the dto', function (): void {
    $relay = SdrRelayFactory::new()->make();

    expect($relay->ipv4)->toBe('155.133.248.36')
        ->and($relay->minPort)->toBe(27015)
        ->and($relay->maxPort)->toBe(27060);
});

it('overrides the relay address and ports', function (): void {
    $relay = SdrRelayFactory::new()->ipv4('155.133.230.98')->ports(minPort: 27015, maxPort: 27030);

    expect($relay->toArray()['port_range'])->toBe([27015, 27030]);

    $relay = $relay->make();

    expect($relay->ipv4)->toBe('155.133.230.98')
        ->and($relay->minPort)->toBe(27015)
        ->and($relay->maxPort)->toBe(27030);
});

it('overrides arbitrary relay keys through state', function (): void {
    expect(SdrRelayFactory::new()->state(['ipv4' => '155.133.230.99'])->make()->ipv4)->toBe('155.133.230.99');
});

// SdrPointOfPresenceFactory

it('builds a point of presence payload without its code', function (): void {
    expect(SdrPointOfPresenceFactory::new()->toArray())->toBe([
        'desc' => 'Amsterdam (Netherlands)',
        'geo' => [4.9, 52.37],
        'relays' => [SdrRelayFactory::new()->toArray()],
    ]);
});

it('maps a point of presence payload onto the dto', function (): void {
    $pointOfPresence = SdrPointOfPresenceFactory::new()->make();

    expect($pointOfPresence->code)->toBe('ams')
        ->and($pointOfPresence->description)->toBe('Amsterdam (Netherlands)')
        ->and($pointOfPresence->latitude)->toBe(52.37)
        ->and($pointOfPresence->longitude)->toBe(4.9)
        ->and($pointOfPresence->aliases)->toBeEmpty()
        ->and($pointOfPresence->relays)->toHaveCount(1)
        ->and($pointOfPresence->relays[0]->ipv4)->toBe('155.133.248.36');
});

it('writes the coordinates in valves longitude, latitude order', function (): void {
    $pointOfPresence = SdrPointOfPresenceFactory::new()->latitude(52.22)->longitude(21.0);

    expect($pointOfPresence->toArray()['geo'])->toBe([21.0, 52.22])
        ->and($pointOfPresence->make()->latitude)->toBe(52.22)
        ->and($pointOfPresence->make()->longitude)->toBe(21.0);
});

it('keeps the other coordinate when setting one', function (): void {
    expect(SdrPointOfPresenceFactory::new()->latitude(1.5)->toArray()['geo'])->toBe([4.9, 1.5])
        ->and(SdrPointOfPresenceFactory::new()->longitude(2.5)->toArray()['geo'])->toBe([2.5, 52.37]);
});

it('overrides the point of presence code and description', function (): void {
    $pointOfPresence = SdrPointOfPresenceFactory::new()->code('waw')->description('Warsaw (Poland)');

    expect($pointOfPresence->code)->toBe('waw')
        ->and($pointOfPresence->toArray())->not->toHaveKey('code')
        ->and($pointOfPresence->make()->code)->toBe('waw')
        ->and($pointOfPresence->make()->description)->toBe('Warsaw (Poland)');
});

it('adds aliases to a point of presence', function (): void {
    $pointOfPresence = SdrPointOfPresenceFactory::new()->aliases('mwh', 'wnt');

    expect($pointOfPresence->toArray()['aliases'])->toBe(['mwh', 'wnt'])
        ->and($pointOfPresence->make()->aliases)->toBe(['mwh', 'wnt']);
});

it('replaces the relays of a point of presence', function (): void {
    $relays = SdrPointOfPresenceFactory::new()->relays(
        SdrRelayFactory::new(),
        SdrRelayFactory::new()->ipv4('155.133.248.37'),
    )->make()->relays;

    expect($relays)->toHaveCount(2)
        ->and($relays[0]->ipv4)->toBe('155.133.248.36')
        ->and($relays[1]->ipv4)->toBe('155.133.248.37');
});

it('drops the relays key from a point of presence without relays', function (): void {
    $pointOfPresence = SdrPointOfPresenceFactory::new()->withoutRelays();

    expect($pointOfPresence->toArray())->not->toHaveKey('relays')
        ->and($pointOfPresence->make()->relays)->toBeEmpty();
});

it('keeps the point of presence code through state and a dropped key', function (): void {
    $pointOfPresence = SdrPointOfPresenceFactory::new()->code('eat');

    expect($pointOfPresence->state(['desc' => 'Wenatchee (Washington)'])->code)->toBe('eat')
        ->and($pointOfPresence->withoutRelays()->code)->toBe('eat');
});

it('overrides arbitrary point of presence keys through state', function (): void {
    expect(SdrPointOfPresenceFactory::new()->state(['desc' => 'Wenatchee (Washington)'])->make()->description)
        ->toBe('Wenatchee (Washington)');
});

// SdrConfigFactory

it('builds an sdr config payload', function (): void {
    expect(SdrConfigFactory::new()->toArray())->toBe([
        'revision' => 1790374330,
        'pops' => ['ams' => SdrPointOfPresenceFactory::new()->toArray()],
    ]);
});

it('maps an sdr config payload onto the dto', function (): void {
    $config = SdrConfigFactory::new()->make();

    expect($config->revision)->toBe(1790374330)
        ->and(array_keys($config->pointsOfPresence))->toBe(['ams'])
        ->and($config->pointsOfPresence['ams']->code)->toBe('ams');
});

it('keys each point of presence by its own code', function (): void {
    $config = SdrConfigFactory::new()->pointsOfPresence(
        SdrPointOfPresenceFactory::new(),
        SdrPointOfPresenceFactory::new()->code('waw'),
    );

    expect($config->toArray()['pops'])->toBe([
        'ams' => SdrPointOfPresenceFactory::new()->toArray(),
        'waw' => SdrPointOfPresenceFactory::new()->toArray(),
    ])
        ->and($config->make()->pointsOfPresence['waw']->code)->toBe('waw');
});

it('overrides the sdr config revision', function (): void {
    expect(SdrConfigFactory::new()->revision(1790374331)->make()->revision)->toBe(1790374331);
});

it('overrides arbitrary sdr config keys through state', function (): void {
    expect(SdrConfigFactory::new()->state(['pops' => []])->make()->pointsOfPresence)->toBeEmpty();
});

// NewsItemFactory

it('builds a news item payload', function (): void {
    expect(NewsItemFactory::new()->toArray())->toBe([
        'gid' => '1838407329261909',
        'title' => 'Valve is still working on the Mann vs Machine update',
        'url' => 'https://steamstore-a.akamaihd.net/news/externalpost/PC Gamer/1838407329261909',
        'is_external_url' => true,
        'author' => 'Rick Lane',
        'contents' => '<p>Valve says the Mann vs Machine update it announced last year is still in the works.</p>',
        'feedlabel' => 'PC Gamer',
        'date' => 1784381770,
        'feedname' => 'PC Gamer',
        'feed_type' => 0,
        'appid' => 440,
    ]);
});

it('maps a news item payload onto the dto', function (): void {
    $item = NewsItemFactory::new()->make();

    expect($item->id)->toBe('1838407329261909')
        ->and($item->title)->toBe('Valve is still working on the Mann vs Machine update')
        ->and($item->url)->toBe('https://steamstore-a.akamaihd.net/news/externalpost/PC Gamer/1838407329261909')
        ->and($item->isExternalUrl)->toBeTrue()
        ->and($item->author)->toBe('Rick Lane')
        ->and($item->contents)->toBe('<p>Valve says the Mann vs Machine update it announced last year is still in the works.</p>')
        ->and($item->feedLabel)->toBe('PC Gamer')
        ->and($item->feedName)->toBe('PC Gamer')
        ->and($item->isCommunityAnnouncement)->toBeFalse()
        ->and($item->publishedAt->getTimestamp())->toBe(1784381770)
        ->and($item->appId)->toBe(440)
        ->and($item->tags)->toBeEmpty();
});

it('overrides the news item id, title and app id', function (): void {
    $item = NewsItemFactory::new()->id('1845383656394827')->title('Team Fortress 2 Update Released')->appId(1245620)->make();

    expect($item->id)->toBe('1845383656394827')
        ->and($item->title)->toBe('Team Fortress 2 Update Released')
        ->and($item->appId)->toBe(1245620);
});

it('writes the publication date as a unix timestamp', function (): void {
    $item = NewsItemFactory::new()->publishedAt(new DateTimeImmutable('2026-10-01 12:00:00 UTC'));

    expect($item->toArray()['date'])->toBe(1790856000)
        ->and($item->make()->publishedAt->getTimestamp())->toBe(1790856000);
});

it('files a news item under the community announcements feed', function (): void {
    $item = NewsItemFactory::new()->communityAnnouncement();

    expect($item->toArray())->toMatchArray([
        'feedlabel' => 'Community Announcements',
        'feedname' => 'steam_community_announcements',
        'feed_type' => 1,
    ])
        ->and($item->make()->isCommunityAnnouncement)->toBeTrue();
});

it('links a news item to the steam store', function (): void {
    $item = NewsItemFactory::new()->internalUrl()->make();

    expect($item->url)->toBe('https://store.steampowered.com/news/285564/')
        ->and($item->isExternalUrl)->toBeFalse();
});

it('links a news item to a given steam store page', function (): void {
    expect(NewsItemFactory::new()->internalUrl('https://store.steampowered.com/news/285419/')->make()->url)
        ->toBe('https://store.steampowered.com/news/285419/');
});

it('reads an empty author as no author at all', function (): void {
    $item = NewsItemFactory::new()->withoutAuthor();

    expect($item->toArray()['author'])->toBe('')
        ->and($item->make()->author)->toBeNull();
});

it('tags a news item', function (): void {
    $item = NewsItemFactory::new()->tags('patchnotes', 'workshop');

    expect($item->toArray()['tags'])->toBe(['patchnotes', 'workshop'])
        ->and($item->make()->tags)->toBe(['patchnotes', 'workshop']);
});

it('overrides arbitrary news item keys through state', function (): void {
    expect(NewsItemFactory::new()->state(['feedname' => 'tf2_blog'])->make()->feedName)->toBe('tf2_blog');
});

// AppNewsFactory

it('builds an app news payload around a single item', function (): void {
    expect(AppNewsFactory::new()->toArray())->toBe([
        'appid' => 440,
        'newsitems' => [NewsItemFactory::new()->toArray()],
        'count' => 1,
    ]);
});

it('maps an app news payload onto the dto', function (): void {
    $news = AppNewsFactory::new()->make();

    expect($news->appId)->toBe(440)
        ->and($news->items)->toHaveCount(1)
        ->and($news->items[0]->id)->toBe('1838407329261909')
        ->and($news->total)->toBe(1);
});

it('counts the news items it replaces', function (): void {
    $news = AppNewsFactory::new()
        ->items(
            NewsItemFactory::new(),
            NewsItemFactory::new()->id('1838407329261910'),
        )
        ->make();

    expect($news->total)->toBe(2)
        ->and($news->items)->toHaveCount(2)
        ->and($news->items[1]->id)->toBe('1838407329261910');
});

it('keeps the news total apart from the items listed', function (): void {
    $news = AppNewsFactory::new()
        ->items(NewsItemFactory::new())
        ->total(3939)
        ->make();

    expect($news->total)->toBe(3939)
        ->and($news->items)->toHaveCount(1);
});

it('lists no news as an empty list counted zero', function (): void {
    $news = AppNewsFactory::new()->items();

    expect($news->toArray())->toBe(['appid' => 440, 'newsitems' => [], 'count' => 0])
        ->and($news->make()->items)->toBeEmpty();
});

it('overrides the app news app id', function (): void {
    $news = AppNewsFactory::new()->appId(2778580)->items(NewsItemFactory::new()->appId(1245620))->make();

    expect($news->appId)->toBe(2778580)
        ->and($news->items[0]->appId)->toBe(1245620);
});

it('overrides arbitrary app news keys through state', function (): void {
    expect(AppNewsFactory::new()->state(['count' => 7])->make()->total)->toBe(7);
});
