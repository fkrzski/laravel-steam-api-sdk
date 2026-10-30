# Laravel Steam API SDK

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="art/banner-dark.svg">
  <img src="art/banner-light.svg" alt="Laravel Steam API SDK — composer require fkrzski/laravel-steam-api-sdk">
</picture>

[![License](https://img.shields.io/packagist/l/fkrzski/laravel-steam-api-sdk.svg?style=for-the-badge)](https://packagist.org/packages/fkrzski/laravel-steam-api-sdk)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/fkrzski/laravel-steam-api-sdk.svg?style=for-the-badge)](https://packagist.org/packages/fkrzski/laravel-steam-api-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/fkrzski/laravel-steam-api-sdk.svg?style=for-the-badge)](https://packagist.org/packages/fkrzski/laravel-steam-api-sdk)
[![Tests](https://img.shields.io/github/actions/workflow/status/fkrzski/laravel-steam-api-sdk/tests.yml?branch=master&label=tests&style=for-the-badge)](https://github.com/fkrzski/laravel-steam-api-sdk/actions/workflows/tests.yml)

Laravel bridge for [`fkrzski/php-steam-api-sdk`](https://github.com/fkrzski/php-steam-api-sdk). Ships a service provider, a `Steam` facade and a `Steam::fake()` test helper so you can talk to the [Steam Web API](https://steamcommunity.com/dev) the Laravel way.

It powers the [player stats](https://deadbystats.eu) on
[Dead by Stats](https://deadbystats.eu).

- Auto-discovered `SteamConnector` binding, scoped per request and safe under Octane.
- Rate limits shared across processes through a Laravel cache store of your choosing — Steam's daily quota or your own, with a cool-down after a 429.
- Timeouts and retries from the config file, and queue middleware that holds a job back once a limit is spent.
- Requests through Guzzle, reported to Laravel Nightwatch while it is enabled, or through any Saloon sender the container builds.
- Fluent `Steam` facade over the SDK's resources — `Steam::users()->summaries(...)` and the rest — plus a lazy news feed.
- Laravel events for every Steam request, response and failure, and a log channel built on them.
- Localised payloads follow your application locale, or the language you pin in config.
- `AsSteamId` Eloquent cast, `SteamIdRule` validation rule and one-liner test fakes that mirror the resources — `Steam::fake()->users()->summaries(...)` — with DTO factories for the payloads.

## Requirements

- PHP **8.5+**
- Laravel **13+**

## Installation

```bash
composer require fkrzski/laravel-steam-api-sdk
```

The service provider and `Steam` facade are auto-discovered. Run the installer to publish the config and store your key:

```bash
php artisan steam:install
```

It writes `config/steam-api.php` and asks for your [Steam Web API key](https://steamcommunity.com/dev), appending it to `.env`. Both steps are also fine to do by hand:

```bash
php artisan vendor:publish --tag=steam-api-config
```

```dotenv
STEAM_API_KEY=your-steam-web-api-key
```

## Quick start

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

$id = SteamId::fromSteamId64('76561198000000000');

$summaries    = Steam::users()->summaries([$id]);
$bans         = Steam::users()->bans([$id]);
$friends      = Steam::users()->friends($id);
$groups       = Steam::users()->groups($id);
$resolvedId   = Steam::users()->resolveVanityUrl('gabelogannewell');
$library      = Steam::players()->ownedGames($id, appIdsFilter: [381210]);
$recentGames  = Steam::players()->recentlyPlayedGames($id);
$level        = Steam::players()->steamLevel($id);
$badges       = Steam::players()->badges($id);
$quests       = Steam::players()->communityBadgeProgress($id);
$stats        = Steam::stats()->userStats($id, appId: 381210);
$achievements = Steam::stats()->achievements($id, appId: 381210);
$playing      = Steam::stats()->currentPlayers(appId: 381210);
$globalStats  = Steam::stats()->globalAchievements(gameId: 381210);
$gameSchema   = Steam::stats()->schema(appId: 381210);
$versionCheck = Steam::apps()->upToDateCheck(appId: 440, version: $installedVersion);
$servers      = Steam::apps()->serversAtAddress('108.181.62.21');
$relayNetwork = Steam::apps()->sdrConfig(appId: 730);
$news         = Steam::news()->appNews(appId: 440);
$newsFeed     = Steam::newsFeed(appId: 440);
$serverTime   = Steam::webApi()->serverTime();
$webApis      = Steam::webApi()->supportedApis();
```

Each resource method returns a strongly-typed DTO from the underlying SDK — you never touch raw JSON.

## Documentation

Full documentation lives at **[docs.fkrzski.dev/laravel-steam-api-sdk](https://docs.fkrzski.dev/laravel-steam-api-sdk)**:

- [Guide](https://docs.fkrzski.dev/laravel-steam-api-sdk/guide) — the `SteamId` value object, the resources, exceptions, and concurrent requests.
- [Configuration](https://docs.fkrzski.dev/laravel-steam-api-sdk/configuration) — the config file, your API key, timeouts and retries, the HTTP sender and Nightwatch.
- [Rate limiting](https://docs.fkrzski.dev/laravel-steam-api-sdk/rate-limiting) — the daily budget, limits of your own, the 429 cool-down and queued jobs.
- [API reference](https://docs.fkrzski.dev/laravel-steam-api-sdk/api-reference) — every resource and facade method, its parameters, return type, and errors.
- [Eloquent cast](https://docs.fkrzski.dev/laravel-steam-api-sdk/eloquent-cast) — persist a Steam ID on a model with `AsSteamId`.
- [Route binding](https://docs.fkrzski.dev/laravel-steam-api-sdk/route-binding) — opt in to resolving a `{steamId}` route parameter into a `SteamId` value object.
- [Validation rule](https://docs.fkrzski.dev/laravel-steam-api-sdk/validation) — validate submitted Steam IDs with `SteamIdRule`.
- [Events](https://docs.fkrzski.dev/laravel-steam-api-sdk/events) — listen to Steam requests, responses and failures through Laravel events.
- [Logging](https://docs.fkrzski.dev/laravel-steam-api-sdk/logging) — log failed Steam calls, and every response if you ask, to a channel of yours.
- [Testing](https://docs.fkrzski.dev/laravel-steam-api-sdk/testing) — fake the Steam Web API with `Steam::fake()`.
- [Versioning](https://docs.fkrzski.dev/laravel-steam-api-sdk#versioning) — what SemVer covers from 1.0.0, and what is `@internal`.

## License

MIT. See [LICENSE.md](LICENSE.md).
