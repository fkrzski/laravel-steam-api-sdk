---
title: Testing
description: Fake the Steam Web API in your tests with Steam::fake() — attach a Saloon MockClient to the connector and assert on the requests your code sends.
---

`Steam::fake()` attaches a Saloon [`MockClient`](https://docs.saloon.dev/testing/recording-responses)
to the singleton connector and returns it for assertions, so your tests never hit
the real Steam Web API.

## Faking responses

Pass a map of request class → `MockResponse`. Every matching request the code
under test sends is answered from the map instead of the network:

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\SteamApiSdk\Http\Requests\GetPlayerSummariesRequest;
use Saloon\Http\Faking\MockResponse;

$mock = Steam::fake([
    GetPlayerSummariesRequest::class => MockResponse::make([
        'response' => ['players' => [/* ... */]],
    ]),
]);

// ... exercise code that calls Steam::playerSummaries() ...
```

Because the fake is attached to the shared connector singleton, it covers every
call made through the facade — the request helpers, `pool()`, and `send()` alike.

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
use Fkrzski\SteamApiSdk\Http\Requests\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockResponse;

it('fetches player summaries', function (): void {
    $mock = Steam::fake([
        GetPlayerSummariesRequest::class => MockResponse::make([
            'response' => ['players' => [/* ... */]],
        ]),
    ]);

    Steam::playerSummaries([SteamId::fromSteamId64('76561198000000000')]);

    $mock->assertSent(GetPlayerSummariesRequest::class);
});
```
