---
title: Configuration
description: Configure the Steam bridge — the published config file, your Steam Web API key, and the cache-backed rate-limit budget shared across every process.
---

The bridge wires the `SteamConnector` for you from a single config file. In most
apps the only value you set is your API key; everything else has a sensible
Laravel-native default.

## Installing

One command covers both setup steps:

```bash
php artisan steam:install
```

It publishes `config/steam-api.php`, then prompts for your
[Steam Web API key](https://steamcommunity.com/dev) — hidden as you type it — and
appends `STEAM_API_KEY` to `.env`.

Re-running it is safe: an existing config file is kept, an existing key is only
replaced once you confirm, and a blank answer leaves `.env` alone. Both steps are
just as fine to do by hand, as below.

## The config file

The service provider merges its own `steam-api` config, so the package works
without publishing anything. Publish it only when you want the file in your repo
to override defaults:

```bash
php artisan vendor:publish --tag=steam-api-config
```

This writes `config/steam-api.php`:

```php
return [

    // Your Steam Web API key, obtained from https://steamcommunity.com/dev.
    // Sent as the "key" query parameter on every request to api.steampowered.com.
    'key' => env('STEAM_API_KEY'),

    // Resolve a route parameter into a SteamId value object. Opt-in. See the
    // route binding guide for details.
    'route_binding' => [
        'enabled' => false,
        'parameter' => 'steamId',
    ],

];
```

## The API key

Set your key in `.env` — it is read through `config('steam-api.key')` and appended
to every request, so you never pass it per call:

```dotenv
STEAM_API_KEY=your-steam-web-api-key
```

Get a key from the [Steam Web API dashboard](https://steamcommunity.com/dev).

If the key is missing — unset, or present but blank — the bridge throws
`SteamApiKeyMissingException` naming both `STEAM_API_KEY` and the `steam-api.key`
config value. The check runs when the connector is first built, not at boot, so an
application still boots, caches its config, and publishes the config file before a key
is ever set; the error surfaces on your first Steam call.

## Route binding

The `route_binding` block controls the `steamId` route binding, which resolves a
route parameter straight into a `SteamId` value object. It is **off by default** —
the parameter name is claimed application-wide, so the only routes it can claim are
your own. Set `enabled` to `true` to opt in; see
[Route binding](/laravel-steam-api-sdk/route-binding) for the full guide.

## The connector binding

The provider binds `SteamConnector` as a **scoped** instance: one per request,
torn down with it. Under [Laravel Octane](https://laravel.com/docs/octane) — and
between queue jobs — the container outlives the request, and a shared connector
would carry its API key into the next one.

It resolves lazily, so an application without a key can still boot. Reach it via
[`Steam::connector()`](/laravel-steam-api-sdk/api-reference#connector), and only for
the current request.

## Rate limiting

The Steam Web API allows **100 000 requests per API key per day**. The connector
enforces this through [`saloonphp/rate-limit-plugin`](https://github.com/saloonphp/rate-limit-plugin)
and throws `SteamRateLimitException` once the budget is spent.

The bridge points the plugin at a `LaravelCacheStore` backed by your **default
cache store**, so the daily budget is tracked wherever your cache lives — not in
per-process memory. With a shared driver (Redis, database, Memcached) every FPM
worker, queue worker, and Octane process draws down one counter:

```dotenv
CACHE_STORE=redis
```

With the `array` or `file` cache driver the budget is not shared across processes
— fine for local development, but pick a shared driver in production so the daily
quota is enforced app-wide.

## Checking your setup

The provider registers a **Steam API** section on Laravel's `about` command:

```bash
php artisan about --only=steam_api
```

```text
  Steam API ...........................................................
  API Key .................................................. ********4f2a
  Daily Requests Remaining ........................... 99,412 of 100,000
  Rate Limit Store ................................................ redis
  Route Binding ....................................... enabled (steamId)
```

- **API Key** — the configured key, masked down to its last four characters so
  the output stays safe to paste into a bug report. Shows `NOT SET` when no key
  is configured.
- **Rate Limit Store** — the cache store the daily counter lives in, i.e. your
  `cache.default`.
- **Daily Requests Remaining** — what is left of the 100 000 request budget,
  read from that store. Shows `UNKNOWN` while no key is set, since the counter
  belongs to the connector.
- **Route Binding** — `disabled`, or `enabled` with the route parameter name it
  claims application-wide.

Nothing here runs on boot: the values are computed only when the command renders,
and no request is ever sent to Steam.
