---
title: Testing
description: Fake the Steam Web API with Steam::fake() — build responses from DTO factories, fake the failure shapes, and assert on the requests you send.
---

`Steam::fake()` attaches a Saloon [`MockClient`](https://docs.saloon.dev/testing/recording-responses)
to the connector and returns it for assertions, so your tests never hit the real
Steam Web API.

## Faking responses

Pass a map of request class → response. `SteamResponse` builds one for every
endpoint the facade wraps, filling it from the [DTO factories](#dto-factories):

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;

$mock = Steam::fake([
    GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
        PlayerSummaryFactory::new()->personaName('Gabe')->online(),
    ),
]);

$summaries = Steam::playerSummaries([$steamId]);
```

Every builder takes as many factories as you want, so an empty call fakes an
empty result:

```php
SteamResponse::playerSummaries();                       // no players matched
SteamResponse::friendList($first, $second, $third);     // three friends
```

The fake is attached to the connector, so it covers every call made through the
facade — the request helpers, `pool()`, and `send()` alike. The binding is scoped,
so the fake goes away with the request that installed it.

`SteamResponse` exists because no two endpoints agree on where the collection
goes: `GetPlayerBans` returns it at the top level, `GetFriendList` under
`friendslist.friends`, `GetOwnedGames` under `response.games` alongside a
`game_count` the SDK reads to decide whether the profile is public at all.
Hand-written envelopes get these wrong quietly.

| Builder | Request |
| --- | --- |
| `SteamResponse::playerSummaries()` | `GetPlayerSummariesRequest` |
| `SteamResponse::playerBans()` | `GetPlayerBansRequest` |
| `SteamResponse::friendList()` | `GetFriendListRequest` |
| `SteamResponse::userGroupList()` | `GetUserGroupListRequest` |
| `SteamResponse::ownedGames()` | `GetOwnedGamesRequest` |
| `SteamResponse::userStats()` | `GetUserStatsForGameRequest` |
| `SteamResponse::playerAchievements()` | `GetPlayerAchievementsRequest` |
| `SteamResponse::vanityUrl()` | `ResolveVanityUrlRequest` |

## DTO factories

Every DTO the facade returns has a factory that builds the payload Steam sends
for it. `::new()` gives a complete, realistic record you can narrow as needed:

```php
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;

PlayerSummaryFactory::new()->toArray();   // the raw payload
PlayerSummaryFactory::new()->make();      // the PlayerSummary DTO
```

`make()` is what you want when a test needs the DTO directly — seeding a
repository, or exercising a method that takes a `PlayerSummary` without any HTTP
in the picture.

Named states cover the variations worth naming, and compose:

```php
PlayerSummaryFactory::new()->private();                      // hidden profile
PlayerSummaryFactory::new()->online()->inGame('730', 'CS2'); // online, in a game
PlayerBanFactory::new()->vacBanned(2)->communityBanned();
OwnedGameFactory::new()->withAppInfo()->neverPlayed();
PlayerAchievementFactory::new()->locked();
UserStatsFactory::new()->empty();
```

`state()` is the escape hatch for anything a named state doesn't cover — it takes
raw payload keys and merges them over the defaults:

```php
PlayerSummaryFactory::new()->state(['profileurl' => 'https://example.test/']);
```

The container factories take the item factories:

```php
UserStatsFactory::new()->stats(
    UserStatFactory::new()->name('DBD_KillerSkulls')->value(1200),
);

PlayerAchievementsFactory::new()->achievements(
    PlayerAchievementFactory::new(),
    PlayerAchievementFactory::new()->locked(),
);
```

Available factories: `PlayerSummaryFactory`, `PlayerBanFactory`, `FriendFactory`,
`UserGroupFactory`, `OwnedGameFactory`, `UserStatFactory`,
`UserStatAchievementFactory`, `UserStatsFactory`, `PlayerAchievementFactory` and
`PlayerAchievementsFactory`, all under
`Fkrzski\LaravelSteamApiSdk\Testing\Factories`.

## Faking failures

Steam has more than one way of saying no, and which one you need depends on the
endpoint under test:

| Builder | Raises | On |
| --- | --- | --- |
| `SteamResponse::profileNotPublic()` | `ProfileNotPublicException` | `GetFriendListRequest`, `GetUserGroupListRequest` |
| `SteamResponse::ownedGamesNotPublic()` | `ProfileNotPublicException` | `GetOwnedGamesRequest` |
| `SteamResponse::statsRefused()` | `ProfileNotPublicException` | `GetUserStatsForGameRequest` |
| `SteamResponse::statsRefused()` | `StatsUnavailableException` | `GetPlayerAchievementsRequest` |
| `SteamResponse::vanityNotFound()` | `SteamUserNotFoundException` | `ResolveVanityUrlRequest` |
| `SteamResponse::invalidApiKey()` | `InvalidApiKeyException` | any request |
| `SteamResponse::apiKeyMissing()` | `InvalidApiKeyException` | any request |

```php
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;

Steam::fake([
    GetFriendListRequest::class => SteamResponse::profileNotPublic(),
]);

$this->expectException(ProfileNotPublicException::class);

Steam::friendList($steamId);
```

Three shapes rather than one because Steam genuinely answers three ways.
`profileNotPublic()` is a `401`. `ownedGamesNotPublic()` is a **`200`** whose only
tell is a missing `game_count`. `statsRefused()` is a `400` carrying an empty JSON
object, which the two ISteamUserStats endpoints read as different exceptions —
the cause is ambiguous and the SDK does not guess.

Two failures cannot be faked with a response, because neither comes from one:
`SteamRateLimitException` is raised by the rate-limit plugin before a response
exists, and `TooManySteamIdsException` by the request constructor when you pass
more than 100 IDs.

## A key is still required

`Steam::fake()` builds the connector before swapping its HTTP client, so your test
suite needs *some* API key or it hits
[`SteamApiKeyMissingException`](/laravel-steam-api-sdk/configuration#the-api-key). No
request leaves your machine, so the value is irrelevant — set a placeholder in
`phpunit.xml`:

```xml
<env name="STEAM_API_KEY" value="testing"/>
```

## Only in the testing environment

`Steam::fake()` throws `FakeOutsideTestsException` unless the application
environment is `testing`. Nothing detaches the mock once it is attached.

## Asserting

The returned `MockClient` exposes Saloon's assertion helpers:

```php
$mock->assertSent(GetPlayerSummariesRequest::class);
$mock->assertSentCount(1);
```

`assertSent` also accepts a closure for asserting on the request that was sent —
its query parameters, body, or headers — when matching on the class alone isn't
enough.

## Pest example

A complete test with Pest:

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

it('fetches player summaries', function (): void {
    $mock = Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new()->personaName('Gabe'),
        ),
    ]);

    $summaries = Steam::playerSummaries([SteamId::fromSteamId64('76561198000000000')]);

    expect($summaries[0]->personaName)->toBe('Gabe');

    $mock->assertSent(GetPlayerSummariesRequest::class);
});
```
