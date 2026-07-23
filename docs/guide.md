---
title: Guide
description: The Steam facade in practice — building SteamId value objects, calling the request helpers, handling SDK exceptions, and fanning out concurrent requests.
---

Once the package is [installed and configured](/laravel-steam-api-sdk/configuration), the
`Steam` facade is the one entry point you need. Three concepts carry the rest: the
**`SteamId`** value object every helper accepts, the **request helpers** you call,
and the readonly **DTOs** you get back.

## The `SteamId` value object

`SteamId` is the only accepted identifier across the facade — no raw strings. It
comes from the underlying SDK. Build one from a verified 64-bit ID, or parse
untrusted user input:

```php
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

// Strict — throws InvalidSteamIdException on anything but a 17-digit numeric ID.
$id = SteamId::fromSteamId64('76561198000000000');

// Lenient — returns null for anything that is not a SteamID64 or /profiles/<id> URL.
$id = SteamId::tryFromInput('https://steamcommunity.com/profiles/76561198000000000');
```

The underlying value is available as `$id->value` (or `(string) $id`), and two IDs
compare with `$id->equals($other)`. The same value object is what the
[`AsSteamId`](/laravel-steam-api-sdk/eloquent-cast) cast stores on your models.

## Calling the facade

Every helper on the facade builds the matching Steam request, sends it through the
shared connector, and decodes the response into typed DTOs — so you work with
objects, never arrays:

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;

$summaries    = Steam::playerSummaries([$id]);
$library      = Steam::ownedGames($id, appIdsFilter: [381210]);
$stats        = Steam::userStatsForGame($id, appId: 381210);
$achievements = Steam::playerAchievements($id, appId: 381210);
$resolvedId   = Steam::resolveVanityUrl('gabelogannewell');
```

Which helper returns which DTO is listed in the
[API reference](/laravel-steam-api-sdk/api-reference). The DTOs themselves — their
properties and enums — are documented in the underlying SDK's
[Data objects](/php-steam-api-sdk/dto-reference) reference.

## Concurrent requests

Use `pool()` to fan out several requests at once instead of sending them serially:

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\SteamApiSdk\Http\Requests\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\GetPlayerSummariesRequest;
use Saloon\Http\Response;

Steam::pool(
    requests: [
        new GetOwnedGamesRequest($id, [381210]),
        new GetPlayerSummariesRequest([$id]),
    ],
    concurrency: 2,
    responseHandler: fn (Response $response) => $response->dto(),
)->send()->wait();
```

The pool works with the raw Saloon requests, so you decode each `Response`
yourself with `->dto()` inside the handler.

## Escape hatch

Need the raw connector or a custom request the helpers don't cover? Reach for them
directly:

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;

$connector = Steam::connector();     // the underlying SteamConnector
$response  = Steam::send($request);  // send any Saloon Request, get the raw Response
```

## Exceptions

The connector uses Saloon's `AlwaysThrowOnErrors`, so a non-2xx response raises a
Saloon exception before you ever see a DTO. Domain-level problems surface as SDK
exceptions instead — every one extends `SteamApiException`, so a single catch
handles them all:

```text
SteamApiException                (root, extends RuntimeException)
├── InvalidSteamIdException      Malformed SteamID64.
├── SteamUserNotFoundException   Vanity URL unresolved or profile missing.
├── ProfileNotPublicException    Profile, games list, or stats are private.
├── TooManySteamIdsException     More than 100 IDs in a batch request.
└── SteamRateLimitException      Daily quota reached; exposes the offending Limit.
```

Catch the leaf you care about, or the root `SteamApiException` to handle every SDK
failure uniformly. The [API reference](/laravel-steam-api-sdk/api-reference) notes which
helper throws which exception.
