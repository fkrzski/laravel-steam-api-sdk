---
title: API reference
description: Every method on the Steam facade — its signature, the value object or DTO it returns, and the exceptions it can raise, each with a runnable example.
---

Every method here lives on the `Steam` facade
(`Fkrzski\LaravelSteamApiSdk\Facades\Steam`). The request helpers accept a
[`SteamId`](/laravel-steam-api-sdk/guide#the-steamid-value-object) wherever they identify a
user, and return readonly DTOs from the underlying SDK — their shapes are
documented in [Data objects](/php-steam-api-sdk/dto-reference).

## Request helpers

### `playerSummaries()`

```text
Steam::playerSummaries(list<SteamId> $steamIds): list<PlayerSummary>
```

Fetches public profile summaries for a batch of players. Accepts **1 to 100** IDs.

- Returns `list<PlayerSummary>`.
- Throws `TooManySteamIdsException` when more than 100 IDs are passed.

```php
$summaries = Steam::playerSummaries([$steamId]);

foreach ($summaries as $summary) {
    echo $summary->personaName, ' — ', $summary->profileUrl, PHP_EOL;
}
```

### `playerBans()`

```text
Steam::playerBans(list<SteamId> $steamIds): list<PlayerBan>
```

Fetches the ban record of a batch of players — community, VAC, game and economy
bans. Accepts **1 to 100** IDs. Ban records are public, so a hidden profile still
returns a row.

- Returns `list<PlayerBan>`.
- Throws `TooManySteamIdsException` when more than 100 IDs are passed.

```php
$bans = Steam::playerBans([$steamId]);

foreach ($bans as $ban) {
    echo $ban->steamId->value, ' — ', $ban->isVacBanned ? 'VAC banned' : 'clean', PHP_EOL;
}
```

### `friendList()`

```text
Steam::friendList(SteamId $steamId, ?FriendRelationship $relationship = null): list<Friend>
```

Lists a player's friends, each with the relationship and the date it started.
`relationship` narrows the result — pass the `FriendRelationship` enum
(`FriendRelationship::Friend` or `FriendRelationship::All`); omit it to let Steam
decide.

- Returns `list<Friend>`.
- Throws `ProfileNotPublicException` when the friend list is private — unlike
  `ownedGames()`, this endpoint refuses the request with a `401` rather than
  returning an empty payload.

```php
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;

$friends = Steam::friendList($steamId, FriendRelationship::Friend);

foreach ($friends as $friend) {
    echo $friend->steamId->value, ' — since ', $friend->friendSince->format('Y-m-d'), PHP_EOL;
}
```

### `userGroupList()`

```text
Steam::userGroupList(SteamId $steamId): list<UserGroup>
```

Lists the community groups a player belongs to, each as a `UserGroup` carrying its
`gid`. Steam returns group IDs only — resolve names through the community API
yourself.

- Returns `list<UserGroup>`, empty when the player is in no group.
- Throws `ProfileNotPublicException` when the profile is hidden.

```php
$groups = Steam::userGroupList($steamId);

foreach ($groups as $group) {
    echo $group->gid, PHP_EOL;
}
```

### `ownedGames()`

```text
Steam::ownedGames(
    SteamId $steamId,
    list<int> $appIdsFilter = [],
    bool $includeAppInfo = false,
    bool $includePlayedFreeGames = false,
): list<OwnedGame>
```

Lists the games a player owns. `appIdsFilter` narrows the result to specific app
IDs; `includeAppInfo` adds names and icons; `includePlayedFreeGames` includes free
games the player has launched.

- Returns `list<OwnedGame>`.
- Throws `ProfileNotPublicException` when the profile or its games list is hidden.

```php
$library = Steam::ownedGames(
    steamId: $steamId,
    appIdsFilter: [381210],
    includeAppInfo: true,
);
```

### `userStatsForGame()`

```text
Steam::userStatsForGame(SteamId $steamId, int $appId, ?string $language = null): UserStats
```

Returns a player's stats and achievement flags for one game. `language` localises
achievement metadata (e.g. `'english'`).

- Returns `UserStats`.
- Throws `ProfileNotPublicException` when the profile is hidden.

```php
$stats = Steam::userStatsForGame(steamId: $steamId, appId: 381210);

foreach ($stats->stats as $stat) {
    echo $stat->name, ' = ', $stat->value, PHP_EOL;
}
```

### `playerAchievements()`

```text
Steam::playerAchievements(SteamId $steamId, int $appId, ?string $language = null): PlayerAchievements
```

Returns a player's achievements for one game, each with its unlock state and time.
`language` localises the achievement name and description.

- Returns `PlayerAchievements`.
- Throws `ProfileNotPublicException` when the profile is hidden.

```php
$result = Steam::playerAchievements(
    steamId: $steamId,
    appId: 381210,
    language: 'english',
);

foreach ($result->achievements as $achievement) {
    echo $achievement->apiName, ' — ', $achievement->achieved ? 'unlocked' : 'locked', PHP_EOL;
}
```

### `resolveVanityUrl()`

```text
Steam::resolveVanityUrl(string $vanityName): SteamId
```

Resolves a vanity slug (the `<name>` in `steamcommunity.com/id/<name>`) to a
`SteamId`.

- Returns `SteamId`.
- Throws `SteamUserNotFoundException` when the slug does not resolve.

```php
$steamId = Steam::resolveVanityUrl('gabelogannewell');
```

## Low-level helpers

### `pool()`

```text
Steam::pool(
    iterable<Request>|callable $requests = [],
    int|callable $concurrency = 5,
    ?callable $responseHandler = null,
    ?callable $exceptionHandler = null,
): Pool
```

Builds a Saloon request `Pool` for sending Steam requests concurrently. See
[Concurrent requests](/laravel-steam-api-sdk/guide#concurrent-requests) in the guide.

- Returns `Saloon\Http\Pool`.

### `send()`

```text
Steam::send(Saloon\Http\Request $request): Saloon\Http\Response
```

Sends any Saloon `Request` through the shared connector and returns the raw
`Response`. The escape hatch for custom requests the helpers don't cover — call
`->dto()` on the response yourself.

- Returns `Saloon\Http\Response`.

### `connector()`

```text
Steam::connector(): SteamConnector
```

Returns the underlying `SteamConnector` for the current request. Use it for
advanced Saloon features not exposed on the facade. The binding is scoped — do not
hold the instance past the request.

- Returns `Fkrzski\SteamApiSdk\SteamConnector`.

### `fake()`

```text
Steam::fake(array<class-string, mixed> $responses = []): Saloon\Http\Faking\MockClient
```

Attaches a Saloon `MockClient` to the connector and returns it for assertions. See
[Testing](/laravel-steam-api-sdk/testing).

- Returns `Saloon\Http\Faking\MockClient`.
- Throws `FakeOutsideTestsException` unless the application environment is `testing`.
