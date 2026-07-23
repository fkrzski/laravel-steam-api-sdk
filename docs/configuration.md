---
title: Configuration
description: Configure the Steam bridge — the published config file, your Steam Web API key, and the cache-backed rate-limit budget shared across every process.
---

The bridge wires the `SteamConnector` for you from a single config file. In most
apps the only value you set is your API key; everything else has a sensible
Laravel-native default.

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

];
```

## The API key

Set your key in `.env` — it is read through `config('steam-api.key')` and appended
to every request, so you never pass it per call:

```dotenv
STEAM_API_KEY=your-steam-web-api-key
```

Get a key from the [Steam Web API dashboard](https://steamcommunity.com/dev).

## The connector singleton

The provider binds `SteamConnector` as a singleton. It resolves lazily — the
`Steam` facade only builds it on first use — so the binding stays safe under
[Laravel Octane](https://laravel.com/docs/octane), where the container survives
between requests. You never construct the connector yourself; reach it via
[`Steam::connector()`](/laravel-steam-api-sdk/api-reference#connector) if you need the
raw object.

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
