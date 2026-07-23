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

Returns the underlying `SteamConnector` singleton. Use it for advanced Saloon
features not exposed on the facade.

- Returns `Fkrzski\SteamApiSdk\SteamConnector`.

### `fake()`

```text
Steam::fake(array<class-string, mixed> $responses = []): Saloon\Http\Faking\MockClient
```

Attaches a Saloon `MockClient` to the connector and returns it for assertions. See
[Testing](/laravel-steam-api-sdk/testing).

- Returns `Saloon\Http\Faking\MockClient`.
