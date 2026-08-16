# Changelog

All notable changes to `laravel-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Requires `fkrzski/php-steam-api-sdk` `^0.4`, which raises every HTTP failure as an SDK exception instead of a Saloon `RequestException` subclass. Code catching Saloon's `UnauthorizedException` for a private profile has to catch `ProfileNotPublicException` — or the root `SteamApiException` — instead ([#34](https://github.com/fkrzski/laravel-steam-api-sdk/issues/34)).
- The daily rate-limit counter is keyed by API key rather than by connector class, so the budget `php artisan about` reports starts from a fresh count after the upgrade. Only the cached counter is orphaned — the quota Steam enforces is untouched.

## [0.3.0] - 2026-08-15

### Added

- `Steam::friendList()` — wraps `GetFriendListRequest` and returns `list<Friend>`, each entry carrying the friend's `SteamId`, the `FriendRelationship` enum and the `DateTimeImmutable` the friendship started. An optional second argument narrows the result to one relationship. A private friend list is a `401` from Steam, so the call throws Saloon's `UnauthorizedException` rather than returning an empty list.
- `php artisan about` — a "Steam API" section with the masked API key (last four characters only), the cache store backing the rate limit, and the remaining daily request budget. Values are resolved when the command renders, never on boot.
- `php artisan steam:install` — publishes `config/steam-api.php` and appends the prompted `STEAM_API_KEY` to `.env`. Safe to re-run: an existing config file is kept, and an existing key is only replaced once confirmed.

### Changed

- `SteamConnector` and `SteamManager` are bound as **scoped** instances rather than singletons, so both are rebuilt per request. A connector held beyond the request that resolved it is now stale — on a long-lived worker the shared one carried its API key into every later request.
- `Steam::fake()` throws `FakeOutsideTestsException` unless the application environment is `testing`. Nothing detaches the mock once it is attached.
- Every class the package ships is now `final`. The documented extension points go through container rebinding — `SteamIdRouteBinding`, the `SteamConnector` binding — not inheritance, so nothing supported breaks, but a subclass of `SteamManager`, `SteamIdRule`, `AsSteamId` or the provider no longer compiles.
- Requires `fkrzski/php-steam-api-sdk` `^0.3`, which groups its request classes into per-interface subnamespaces — `Http\Requests\ISteamUser`, `Http\Requests\ISteamUserStats` and `Http\Requests\IPlayerService`. The facade helpers are unaffected, but code that names a request class directly — a `Steam::fake()` response map, `assertSent()`, or a request passed to `send()` or `pool()` — has to update its imports.

### Fixed

- `Steam::fake()` no longer leaks its `MockClient` into later requests. The manager resolved the connector from the container captured at registration rather than the one that built it, so under Octane the mock stayed attached to a connector shared by the whole worker.
- A missing Steam Web API key now throws `SteamApiKeyMissingException` naming both the `STEAM_API_KEY` env var and the `steam-api.key` config value, instead of surfacing a `TypeError` from `SteamConfig` inside the container. `env('STEAM_API_KEY')` resolves to `null` when unset, so the default in `config('steam-api.key', '')` never applied. Blank and non-string values are rejected the same way, and a key is trimmed before use. The check runs when the connector is first built rather than at boot, so an application without a key can still boot and publish the config.

## [0.2.0] - 2026-07-29

### Added

- `AsSteamId` Eloquent cast — converts a model attribute to a `SteamId` value object on read and serializes it back to its 64-bit string on write. Values are validated through `SteamId::fromSteamId64`; non-scalar stored values throw `InvalidSteamIdException` and `null` is preserved.
- `SteamId` route binding — a `{steamId}` route parameter is resolved into a `SteamId` value object through `SteamId::tryFromInput` (accepting a 64-bit ID or a `/profiles/<id>` URL), aborting with a 404 on unresolvable input. Enabled by default and configurable via `steam-api.route_binding` (`enabled`, `parameter`); the resolver `SteamIdRouteBinding` is resolved from the container and can be swapped by rebinding it.
- `SteamIdRule` validation rule — validates that an attribute resolves to a `SteamId`, accepting a 64-bit ID or a `/profiles/<id>` URL by default and only a raw 64-bit ID in `strict()` mode. Format-only, so no Steam Web API call is made during validation; `string`, `int` and `Stringable` values are accepted and trimmed. Messages ship as the `steam-api` translation namespace, publishable under the `steam-api-translations` tag.

## [0.1.0] - 2026-06-11

Initial release. Laravel bridge for [`fkrzski/php-steam-api-sdk`](https://github.com/fkrzski/php-steam-api-sdk).

### Added

- `SteamServiceProvider` — auto-discovered; binds `SteamConnector` as a singleton (Octane-safe resolver) wired with the configured API key and the Laravel cache rate-limit store. Publishes `config/steam-api.php` under the `steam-api-config` tag.
- `SteamManager` — thin wrapper over `SteamConnector` exposing `connector()`, `send()`, `pool()` and convenience methods (`playerSummaries()`, `ownedGames()`, `userStatsForGame()`, `playerAchievements()`, `resolveVanityUrl()`).
- `Steam` facade for static access to the manager.
- `Steam::fake()` — attaches a Saloon `MockClient` to the singleton connector and returns it for assertions, removing per-test connector wiring.

[Unreleased]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.3.0...HEAD
[0.3.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/fkrzski/laravel-steam-api-sdk/releases/tag/0.1.0
