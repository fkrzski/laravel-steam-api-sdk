---
title: laravel-steam-api-sdk
description: Laravel bridge for the Steam Web API — an auto-discovered Steam facade, a cache-backed rate-limit budget, an Eloquent cast, and one-liner test fakes.
repository: https://github.com/fkrzski/laravel-steam-api-sdk
packagist: fkrzski/laravel-steam-api-sdk
status: stable
---

The Laravel bridge for [`fkrzski/php-steam-api-sdk`](https://github.com/fkrzski/php-steam-api-sdk).
It ships a service provider, a fluent `Steam` facade, and a `Steam::fake()` test
helper so you can talk to the [Steam Web API](https://steamcommunity.com/dev) the
Laravel way — no manual connector wiring, no raw JSON.

## Why laravel-steam-api-sdk

- **Auto-discovered facade** — the `Steam` facade and its `SteamConnector` singleton register themselves, Octane-safe.
- **Shared rate-limit budget** — the daily quota is tracked through your Laravel cache store, so every process draws on one counter.
- **First-class request helpers** — `Steam::playerSummaries()`, `ownedGames()`, `userStatsForGame()`, and friends return typed DTOs.
- **Eloquent cast** — store a Steam ID on a model and read it back as a `SteamId` value object with the [`AsSteamId`](/laravel-steam-api-sdk/eloquent-cast) cast.
- **One-liner fakes** — `Steam::fake()` swaps in Saloon's `MockClient` for assertions.

## Requirements

- PHP **8.5+**
- Laravel **13+**

## Installation

```bash
composer require fkrzski/laravel-steam-api-sdk
```

The service provider and `Steam` facade are auto-discovered. Publish the config to
override the defaults:

```bash
php artisan vendor:publish --tag=steam-api-config
```

Set your Steam Web API key in `.env`:

```dotenv
STEAM_API_KEY=your-steam-web-api-key
```

## Quickstart

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

$id = SteamId::fromSteamId64('76561198000000000');

$summaries = Steam::playerSummaries([$id]);

echo $summaries[0]->personaName;
```

Each helper sends the request through the shared connector and hands back the
readonly DTO for that request — you never touch the underlying Saloon response.

## Next steps

- [Guide](/laravel-steam-api-sdk/guide) — the `SteamId` value object, the facade helpers, exceptions, and concurrent requests.
- [Configuration](/laravel-steam-api-sdk/configuration) — the config file, your API key, and the cache-backed rate limit.
- [API reference](/laravel-steam-api-sdk/api-reference) — every facade method, its parameters, return type, and errors.
- [Eloquent cast](/laravel-steam-api-sdk/eloquent-cast) — persist a Steam ID on a model with `AsSteamId`.
- [Testing](/laravel-steam-api-sdk/testing) — fake the Steam Web API with `Steam::fake()`.
