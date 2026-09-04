<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Contracts\SteamManager as SteamManagerContract;
use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeOutsideTestsException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLanguageException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\SteamApiKeyMissingException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\BadgeFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\CommunityBadgeQuestFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameSchemaFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GlobalAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\OwnedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBadgesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBanFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGamesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Enums\EconomyBan;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Response;

mutates(SteamManager::class);

it('resolves the connector and manager once per container', function (): void {
    expect(app(SteamConnector::class))->toBe(app(SteamConnector::class))
        ->and(app(SteamManager::class))->toBe(app(SteamManager::class));
});

it('binds the manager contract to the concrete instance', function (): void {
    expect(app(SteamManagerContract::class))->toBe(app(SteamManager::class));
});

it('rebuilds the connector once scoped instances are flushed', function (): void {
    $connector = app(SteamConnector::class);

    app()->forgetScopedInstances();

    expect(app(SteamConnector::class))->not->toBe($connector);
});

// Mirrors Octane, which serves each request from a shallow clone of the app.
it('resolves the connector from the container that built the manager', function (): void {
    $sandbox = clone app();
    $sandbox->forgetScopedInstances();

    $manager = $sandbox->make(SteamManager::class);

    expect($manager->connector())->toBe($sandbox->make(SteamConnector::class))
        ->and($manager->connector())->not->toBe(app(SteamConnector::class));
});

it('merges the package config', function (): void {
    expect(config('steam-api.key'))->toBe('test-steam-api-key');
});

it('configures the connector with the api key', function (): void {
    expect(app(SteamConnector::class)->steamConfig->apiKey)->toBe('test-steam-api-key');
});

it('trims surrounding whitespace from the api key', function (): void {
    config()->set('steam-api.key', '  test-steam-api-key  ');

    expect(app(SteamConnector::class)->steamConfig->apiKey)->toBe('test-steam-api-key');
});

// The connector has to be built without a key, or the endpoints Steam serves
// anonymously are unreachable — the check moves to the request that needs one.
it('builds the connector with a blank key when none is configured', function (mixed $key): void {
    config()->set('steam-api.key', $key);

    expect(app(SteamConnector::class)->steamConfig->apiKey)->toBeEmpty();
})->with([
    'null' => null,
    'empty string' => '',
    'whitespace only' => '   ',
    'integer' => 123,
    'array' => [[]],
    'boolean' => true,
]);

it('throws when a request needing the key is sent without one', function (): void {
    config()->set(['steam-api.key' => null]);

    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(),
    ]);

    expect(fn (): array => Steam::summaries([steamId()]))
        ->toThrow(SteamApiKeyMissingException::class);
});

it('sends an anonymous request with no key configured', function (Closure $call, string $request): void {
    config()->set(['steam-api.key' => null]);

    Steam::fake([
        GetNumberOfCurrentPlayersRequest::class => SteamResponse::currentPlayers(12_345),
        GetGlobalAchievementPercentagesForAppRequest::class => SteamResponse::globalAchievements(
            GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5),
        ),
    ]);

    $call();

    expect(Steam::lastResponse()?->getPendingRequest()->query()->all())->not->toHaveKey('key');

    Steam::assertSent($request);
})->with([
    'current players' => [
        fn (): int => Steam::currentPlayers(appId: 381210),
        GetNumberOfCurrentPlayersRequest::class,
    ],
    'global achievements' => [
        fn (): array => Steam::globalAchievements(gameId: 381210),
        GetGlobalAchievementPercentagesForAppRequest::class,
    ],
]);

it('attaches a fake with no key configured', function (): void {
    config()->set(['steam-api.key' => null]);

    Steam::fake();

    expect(app(SteamConnector::class)->hasMockClient())->toBeTrue();
});

it('names the env var and the config key in the exception', function (): void {
    config()->set(['steam-api.key' => null]);

    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(),
    ]);

    $send = fn (): array => Steam::summaries([steamId()]);

    expect($send)->toThrow(SteamApiKeyMissingException::class, 'STEAM_API_KEY')
        ->and($send)->toThrow(SteamApiKeyMissingException::class, 'steam-api.key');
});

it('configures the connector with english until told otherwise', function (): void {
    expect(app(SteamConnector::class)->steamConfig->language)->toBe(Language::English);
});

it('configures the connector with the language code', function (): void {
    config()->set('steam-api.language', 'polish');

    expect(app(SteamConnector::class)->steamConfig->language)->toBe(Language::Polish);
});

it('trims surrounding whitespace from the language code', function (): void {
    config()->set('steam-api.language', '  polish  ');

    expect(app(SteamConnector::class)->steamConfig->language)->toBe(Language::Polish);
});

it('sends no language once the configured code is blanked out', function (mixed $language): void {
    config()->set('steam-api.language', $language);

    expect(app(SteamConnector::class)->steamConfig->language)->toBeNull();
})->with([
    'null' => null,
    'empty string' => '',
    'whitespace only' => '   ',
    'integer' => 123,
    'array' => [[]],
    'boolean' => true,
]);

it('throws when the configured language is not one of steams codes', function (string $language): void {
    config()->set('steam-api.language', $language);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamLanguageException::class);
})->with([
    'an iso code' => 'en',
    'a case name' => 'Korean',
    'nonsense' => 'klingon',
]);

it('names the env var, the config key and the rejected code in the exception', function (): void {
    config()->set('steam-api.language', 'klingon');

    $resolve = fn (): SteamConnector => app(SteamConnector::class);

    expect($resolve)->toThrow(InvalidSteamLanguageException::class, 'STEAM_API_LANGUAGE')
        ->and($resolve)->toThrow(InvalidSteamLanguageException::class, 'steam-api.language')
        ->and($resolve)->toThrow(InvalidSteamLanguageException::class, 'klingon');
});

it('does not require the api key to reach the connector', function (): void {
    config()->set(['steam-api.key' => null]);

    expect(fn (): SteamManager => app(SteamManager::class))
        ->not->toThrow(SteamApiKeyMissingException::class)
        ->and(fn (): SteamConnector => Steam::connector())
        ->not->toThrow(SteamApiKeyMissingException::class);
});

it('resolves the facade to the manager singleton', function (): void {
    expect(Steam::getFacadeRoot())->toBe(app(SteamManager::class));
});

it('resolves the facade through the manager contract', function (): void {
    $replacement = new SteamManager(fn (): SteamConnector => app(SteamConnector::class), app());

    app()->instance(SteamManagerContract::class, $replacement);

    expect(Steam::getFacadeRoot())->toBe($replacement);
});

it('exposes the underlying connector', function (): void {
    expect(Steam::connector())->toBe(app(SteamConnector::class));
});

it('fakes responses and resolves a vanity url', function (): void {
    $mock = Steam::fake([
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(steamId()),
    ]);

    $steamId = Steam::resolveVanityUrl('gabelogannewell');

    expect($steamId->value)->toBe('76561198000000000');

    $mock->assertSent(ResolveVanityUrlRequest::class);
});

it('does not leak the fake past the scope that installed it', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(steamId()),
    ]);

    // Read through a variable: expecting on the same call twice has PHPStan
    // carry the first result past forgetScopedInstances().
    $fakedInScope = app(SteamConnector::class)->hasMockClient();

    app()->forgetScopedInstances();

    expect($fakedInScope)->toBeTrue()
        ->and(app(SteamConnector::class)->hasMockClient())->toBeFalse();
});

it('refuses to fake outside the testing environment', function (): void {
    app()->instance('env', 'production');

    expect(fn (): MockClient => Steam::fake())
        ->toThrow(FakeOutsideTestsException::class, 'only available while running tests');
});

it('fetches player summaries through the convenience method', function (): void {
    $mock = Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new()->personaName('Gabe'),
        ),
    ]);

    $summaries = Steam::summaries([steamId()]);

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]->personaName)->toBe('Gabe');

    $mock->assertSent(GetPlayerSummariesRequest::class);
});

it('fetches player bans', function (): void {
    $mock = Steam::fake([
        GetPlayerBansRequest::class => SteamResponse::playerBans(
            PlayerBanFactory::new()->vacBanned(2)->economyBan(EconomyBan::Probation),
        ),
    ]);

    $bans = Steam::bans([steamId()]);

    expect($bans)->toHaveCount(1)
        ->and($bans[0]->steamId->value)->toBe('76561198000000000')
        ->and($bans[0]->numberOfVacBans)->toBe(2)
        ->and($bans[0]->economyBan)->toBe(EconomyBan::Probation);

    $mock->assertSent(GetPlayerBansRequest::class);
});

it('batches every steam id into one ban lookup', function (): void {
    $mock = Steam::fake([
        GetPlayerBansRequest::class => SteamResponse::playerBans(),
    ]);

    $ids = [steamId(), SteamId::fromSteamId64('76561198000000001')];

    expect(Steam::bans($ids))->toBeEmpty();

    $mock->assertSent(
        fn (GetPlayerBansRequest $request): bool => $request->steamIds === $ids,
    );
});

it('fetches a friend list', function (): void {
    $mock = Steam::fake([
        GetFriendListRequest::class => SteamResponse::friendList(
            FriendFactory::new()->relationship(FriendRelationship::All),
        ),
    ]);

    $friends = Steam::friends(steamId());

    expect($friends)->toHaveCount(1)
        ->and($friends[0]->relationship)->toBe(FriendRelationship::All);

    $mock->assertSent(GetFriendListRequest::class);
});

it('sends no relationship filter by default', function (): void {
    $mock = Steam::fake([
        GetFriendListRequest::class => SteamResponse::friendList(),
    ]);

    expect(Steam::friends(steamId()))->toBeEmpty();

    $mock->assertSent(
        fn (GetFriendListRequest $request): bool => ! $request->relationship instanceof FriendRelationship,
    );
});

it('narrows the friend list to a relationship', function (): void {
    $mock = Steam::fake([
        GetFriendListRequest::class => SteamResponse::friendList(),
    ]);

    Steam::friends(steamId(), FriendRelationship::All);

    $mock->assertSent(
        fn (GetFriendListRequest $request): bool => $request->relationship === FriendRelationship::All,
    );
});

it('fetches the group list', function (): void {
    $mock = Steam::fake([
        GetUserGroupListRequest::class => SteamResponse::userGroupList(
            UserGroupFactory::new()->gid('103582791429521412'),
        ),
    ]);

    $groups = Steam::groups(steamId());

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->gid)->toBe('103582791429521412');

    $mock->assertSent(
        fn (GetUserGroupListRequest $request): bool => $request->steamId->value === steamId()->value,
    );
});

it('fetches owned games', function (): void {
    $mock = Steam::fake([
        GetOwnedGamesRequest::class => SteamResponse::ownedGames(
            OwnedGameFactory::new()->appId(381210),
        ),
    ]);

    $games = Steam::ownedGames(steamId(), appIdsFilter: [381210]);

    expect($games)->toHaveCount(1)
        ->and($games[0]->appId)->toBe(381210);

    $mock->assertSent(GetOwnedGamesRequest::class);
});

it('fetches recently played games', function (): void {
    $mock = Steam::fake([
        GetRecentlyPlayedGamesRequest::class => SteamResponse::recentlyPlayedGames(
            RecentlyPlayedGamesFactory::new()
                ->games(RecentlyPlayedGameFactory::new()->appId(381210))
                ->totalCount(40),
        ),
    ]);

    $recent = Steam::recentlyPlayedGames(steamId(), count: 1);

    expect($recent->totalCount)->toBe(40)
        ->and($recent->games)->toHaveCount(1)
        ->and($recent->games[0]->appId)->toBe(381210);

    $mock->assertSent(
        fn (GetRecentlyPlayedGamesRequest $request): bool => $request->count === 1,
    );
});

it('fetches the steam level', function (): void {
    $mock = Steam::fake([
        GetSteamLevelRequest::class => SteamResponse::steamLevel(42),
    ]);

    expect(Steam::steamLevel(steamId()))->toBe(42);

    $mock->assertSent(GetSteamLevelRequest::class);
});

it('fetches badges', function (): void {
    $mock = Steam::fake([
        GetBadgesRequest::class => SteamResponse::badges(
            PlayerBadgesFactory::new()->level(42)->badges(
                BadgeFactory::new()->badgeId(13),
                BadgeFactory::new()->badgeId(2)->withoutApp(),
            ),
        ),
    ]);

    $badges = Steam::badges(steamId());

    expect($badges->playerLevel)->toBe(42)
        ->and($badges->badges)->toHaveCount(2)
        ->and($badges->badges[1]->appId)->toBeNull();

    $mock->assertSent(
        fn (GetBadgesRequest $request): bool => $request->steamId->value === steamId()->value,
    );
});

it('fetches community badge progress', function (): void {
    $mock = Steam::fake([
        GetCommunityBadgeProgressRequest::class => SteamResponse::communityBadgeProgress(
            CommunityBadgeQuestFactory::new()->questId(115),
            CommunityBadgeQuestFactory::new()->questId(202)->incomplete(),
        ),
    ]);

    $quests = Steam::communityBadgeProgress(steamId());

    expect($quests)->toHaveCount(2)
        ->and($quests[0]->questId)->toBe(115)
        ->and($quests[0]->completed)->toBeTrue()
        ->and($quests[1]->completed)->toBeFalse();

    $mock->assertSent(
        fn (GetCommunityBadgeProgressRequest $request): bool => $request->steamId->value === steamId()->value,
    );

});

it('fetches user stats for a game', function (): void {
    $mock = Steam::fake([
        GetUserStatsForGameRequest::class => SteamResponse::userStats(
            UserStatsFactory::new()->gameName('Dead by Daylight'),
        ),
    ]);

    $stats = Steam::userStats(steamId(), appId: 381210);

    expect($stats->gameName)->toBe('Dead by Daylight');

    $mock->assertSent(GetUserStatsForGameRequest::class);
});

it('fetches player achievements', function (): void {
    $mock = Steam::fake([
        GetPlayerAchievementsRequest::class => SteamResponse::playerAchievements(
            PlayerAchievementsFactory::new()->gameName('Dead by Daylight'),
        ),
    ]);

    $achievements = Steam::achievements(steamId(), appId: 381210);

    expect($achievements->gameName)->toBe('Dead by Daylight')
        ->and($achievements->achievements)->toHaveCount(1);

    $mock->assertSent(GetPlayerAchievementsRequest::class);
});

it('sends no language with the user stats request by default', function (): void {
    $mock = Steam::fake([
        GetUserStatsForGameRequest::class => SteamResponse::userStats(UserStatsFactory::new()),
    ]);

    Steam::userStats(steamId(), appId: 381210);

    $mock->assertSent(
        fn (GetUserStatsForGameRequest $request): bool => ! $request->language instanceof Language,
    );
});

it('localises the user stats request', function (): void {
    $mock = Steam::fake([
        GetUserStatsForGameRequest::class => SteamResponse::userStats(UserStatsFactory::new()),
    ]);

    Steam::userStats(steamId(), appId: 381210, language: Language::Polish);

    $mock->assertSent(
        fn (GetUserStatsForGameRequest $request): bool => $request->language === Language::Polish,
    );
});

it('sends no language with the achievements request by default', function (): void {
    $mock = Steam::fake([
        GetPlayerAchievementsRequest::class => SteamResponse::playerAchievements(
            PlayerAchievementsFactory::new(),
        ),
    ]);

    Steam::achievements(steamId(), appId: 381210);

    $mock->assertSent(
        fn (GetPlayerAchievementsRequest $request): bool => ! $request->language instanceof Language,
    );
});

it('localises the achievements request', function (): void {
    $mock = Steam::fake([
        GetPlayerAchievementsRequest::class => SteamResponse::playerAchievements(
            PlayerAchievementsFactory::new(),
        ),
    ]);

    Steam::achievements(steamId(), appId: 381210, language: Language::Polish);

    $mock->assertSent(
        fn (GetPlayerAchievementsRequest $request): bool => $request->language === Language::Polish,
    );
});

it('fetches the current player count', function (): void {
    $mock = Steam::fake([
        GetNumberOfCurrentPlayersRequest::class => SteamResponse::currentPlayers(12_345),
    ]);

    expect(Steam::currentPlayers(appId: 381210))->toBe(12_345);

    $mock->assertSent(
        fn (GetNumberOfCurrentPlayersRequest $request): bool => $request->appId === 381210,
    );
});

it('fetches the global achievements for a game', function (): void {
    $mock = Steam::fake([
        GetGlobalAchievementPercentagesForAppRequest::class => SteamResponse::globalAchievements(
            GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5),
        ),
    ]);

    $achievements = Steam::globalAchievements(gameId: 381210);

    expect($achievements)->toHaveCount(1)
        ->and($achievements[0]->apiName)->toBe('ACH_ESCAPE')
        ->and($achievements[0]->percent)->toBe(12.5);

    $mock->assertSent(
        fn (GetGlobalAchievementPercentagesForAppRequest $request): bool => $request->gameId === 381210,
    );
});

it('fetches the schema for a game', function (): void {
    $mock = Steam::fake([
        GetSchemaForGameRequest::class => SteamResponse::gameSchema(
            GameSchemaFactory::new()->gameName('Portal 2'),
        ),
    ]);

    $schema = Steam::schema(appId: 620);

    expect($schema->gameName)->toBe('Portal 2')
        ->and($schema->stats)->toHaveCount(1)
        ->and($schema->achievements)->toHaveCount(1);

    $mock->assertSent(
        fn (GetSchemaForGameRequest $request): bool => $request->appId === 620,
    );
});

// An app publishing no schema answers 200 with every key dropped, which is a DTO
// carrying nothing rather than the failure the status makes it look like.
it('returns an empty schema for an app publishing none', function (): void {
    Steam::fake([
        GetSchemaForGameRequest::class => SteamResponse::gameSchema(
            GameSchemaFactory::new()->empty(),
        ),
    ]);

    $schema = Steam::schema(appId: 620);

    expect($schema->gameName)->toBeNull()
        ->and($schema->gameVersion)->toBeNull()
        ->and($schema->stats)->toBeEmpty()
        ->and($schema->achievements)->toBeEmpty();
});

it('sends no language with the schema request by default', function (): void {
    $mock = Steam::fake([
        GetSchemaForGameRequest::class => SteamResponse::gameSchema(GameSchemaFactory::new()),
    ]);

    Steam::schema(appId: 620);

    $mock->assertSent(
        fn (GetSchemaForGameRequest $request): bool => ! $request->language instanceof Language,
    );
});

it('localises the schema request', function (): void {
    $mock = Steam::fake([
        GetSchemaForGameRequest::class => SteamResponse::gameSchema(GameSchemaFactory::new()),
    ]);

    Steam::schema(appId: 620, language: Language::Polish);

    $mock->assertSent(
        fn (GetSchemaForGameRequest $request): bool => $request->language === Language::Polish,
    );
});

// The connector strips the key while booting the pending request, so the request
// object still carries it — only what the pending request holds says what went out.
it('sends no api key on an anonymous endpoint', function (): void {
    Steam::fake([
        GetNumberOfCurrentPlayersRequest::class => SteamResponse::currentPlayers(12_345),
    ]);

    Steam::currentPlayers(appId: 381210);

    $query = Steam::lastResponse()?->getPendingRequest()->query()->all();

    expect($query)->toHaveKey('appid', 381210)
        ->and($query)->not->toHaveKey('key');
});

it('sends the configured language on every localised request', function (): void {
    config()->set('steam-api.language', 'polish');

    Steam::fake([
        GetSchemaForGameRequest::class => SteamResponse::gameSchema(GameSchemaFactory::new()),
    ]);

    Steam::schema(appId: 620);

    expect(Steam::lastResponse()?->getPendingRequest()->query()->all())->toHaveKey('l', 'polish');
});

it('sends an arbitrary request and returns the raw response', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(steamId()),
    ]);

    $response = Steam::send(new ResolveVanityUrlRequest('gabelogannewell'));

    expect($response->status())->toBe(200);
});

it('sends requests concurrently through a pool', function (): void {
    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(),
    ]);

    $sent = [];

    Steam::pool(
        requests: [new GetPlayerSummariesRequest([steamId()])],
        concurrency: 1,
        responseHandler: function (Response $response) use (&$sent): void {
            $sent[] = $response->status();
        },
    )->send()->wait();

    expect($sent)->toBe([200]);
});
