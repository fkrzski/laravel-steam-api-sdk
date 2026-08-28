<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\BadgeFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\CommunityBadgeQuestFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\OwnedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBadgesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBanFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGamesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\SteamApiSdk\Enums\CommentPermission;
use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;
use Fkrzski\SteamApiSdk\Enums\EconomyBan;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Enums\PersonaState;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

mutates(
    BadgeFactory::class,
    CommunityBadgeQuestFactory::class,
    FriendFactory::class,
    OwnedGameFactory::class,
    PlayerAchievementFactory::class,
    PlayerAchievementsFactory::class,
    PlayerBadgesFactory::class,
    PlayerBanFactory::class,
    PlayerSummaryFactory::class,
    RecentlyPlayedGameFactory::class,
    RecentlyPlayedGamesFactory::class,
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
