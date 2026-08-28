<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeOutsideTestsException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\SteamApiKeyMissingException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\OwnedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBanFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGamesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Enums\EconomyBan;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
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

it('throws when the api key is missing', function (mixed $key): void {
    config()->set('steam-api.key', $key);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(SteamApiKeyMissingException::class);
})->with([
    'null' => null,
    'empty string' => '',
    'whitespace only' => '   ',
    'integer' => 123,
    'array' => [[]],
    'boolean' => true,
]);

it('names the env var and the config key in the exception', function (): void {
    config()->set(['steam-api.key' => null]);

    $resolve = fn (): SteamConnector => app(SteamConnector::class);

    expect($resolve)->toThrow(SteamApiKeyMissingException::class, 'STEAM_API_KEY')
        ->and($resolve)->toThrow(SteamApiKeyMissingException::class, 'steam-api.key');
});

it('does not require the api key until the connector is built', function (): void {
    config()->set(['steam-api.key' => null]);

    expect(fn (): SteamManager => app(SteamManager::class))
        ->not->toThrow(SteamApiKeyMissingException::class)
        ->and(fn (): SteamConnector => Steam::connector())
        ->toThrow(SteamApiKeyMissingException::class);
});

it('resolves the facade to the manager singleton', function (): void {
    expect(Steam::getFacadeRoot())->toBe(app(SteamManager::class));
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

    $summaries = Steam::playerSummaries([steamId()]);

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

    $bans = Steam::playerBans([steamId()]);

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

    expect(Steam::playerBans($ids))->toBeEmpty();

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

    $friends = Steam::friendList(steamId());

    expect($friends)->toHaveCount(1)
        ->and($friends[0]->relationship)->toBe(FriendRelationship::All);

    $mock->assertSent(GetFriendListRequest::class);
});

it('sends no relationship filter by default', function (): void {
    $mock = Steam::fake([
        GetFriendListRequest::class => SteamResponse::friendList(),
    ]);

    expect(Steam::friendList(steamId()))->toBeEmpty();

    $mock->assertSent(
        fn (GetFriendListRequest $request): bool => ! $request->relationship instanceof FriendRelationship,
    );
});

it('narrows the friend list to a relationship', function (): void {
    $mock = Steam::fake([
        GetFriendListRequest::class => SteamResponse::friendList(),
    ]);

    Steam::friendList(steamId(), FriendRelationship::All);

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

    $groups = Steam::userGroupList(steamId());

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

it('fetches user stats for a game', function (): void {
    $mock = Steam::fake([
        GetUserStatsForGameRequest::class => SteamResponse::userStats(
            UserStatsFactory::new()->gameName('Dead by Daylight'),
        ),
    ]);

    $stats = Steam::userStatsForGame(steamId(), appId: 381210);

    expect($stats->gameName)->toBe('Dead by Daylight');

    $mock->assertSent(GetUserStatsForGameRequest::class);
});

it('fetches player achievements', function (): void {
    $mock = Steam::fake([
        GetPlayerAchievementsRequest::class => SteamResponse::playerAchievements(
            PlayerAchievementsFactory::new()->gameName('Dead by Daylight'),
        ),
    ]);

    $achievements = Steam::playerAchievements(steamId(), appId: 381210);

    expect($achievements->gameName)->toBe('Dead by Daylight')
        ->and($achievements->achievements)->toHaveCount(1);

    $mock->assertSent(GetPlayerAchievementsRequest::class);
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
