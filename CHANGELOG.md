# Changelog

All notable changes to `laravel-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-08-21

### Added

- `Steam::playerBans()` — wraps `GetPlayerBansRequest` and returns `list<PlayerBan>` for a batch of up to 100 IDs. Ban records are public, so a hidden profile still returns a row ([#35](https://github.com/fkrzski/laravel-steam-api-sdk/issues/35)).
- `Steam::userGroupList()` — wraps `GetUserGroupListRequest` and returns `list<UserGroup>`, each carrying the group's `gid`. A hidden profile raises `ProfileNotPublicException` rather than returning an empty list ([#35](https://github.com/fkrzski/laravel-steam-api-sdk/issues/35)).
- `php artisan about` — a "Route Binding" row reporting `disabled`, or `enabled` with the route parameter the binding claims ([#41](https://github.com/fkrzski/laravel-steam-api-sdk/issues/41)).
- DTO factories in `Fkrzski\LaravelSteamApiSdk\Testing\Factories` — one for every DTO the facade returns, building the raw Steam payload with `toArray()` or the DTO itself with `make()`. Named states cover the variations worth naming: `->private()`, `->online()`, `->vacBanned()`, `->locked()` ([#36](https://github.com/fkrzski/laravel-steam-api-sdk/issues/36)).
- `SteamResponse` — builds a `MockResponse` for every endpoint the facade wraps, filled from the DTO factories. No two endpoints nest their collection alike, and the failure builders cover the three separate shapes Steam refuses a request with ([#36](https://github.com/fkrzski/laravel-steam-api-sdk/issues/36)).
- `Steam::assertSent()`, `assertNotSent()`, `assertNothingSent()`, `assertSentCount()` and `assertSentInOrder()` — Saloon's `MockClient` assertions on the facade, so a test no longer has to hold the mock `Steam::fake()` returns. Asserting without a fake raises `FakeNotInstalledException` rather than passing on an empty history ([#38](https://github.com/fkrzski/laravel-steam-api-sdk/issues/38)).
- `Steam::recorded()`, `Steam::lastRequest()` and `Steam::lastResponse()` — Saloon's `MockClient` readers on the facade, so a test that inspects the traffic rather than asserting on it no longer has to hold the mock either. Reading without a fake raises `FakeNotInstalledException`, same as the assertions ([#51](https://github.com/fkrzski/laravel-steam-api-sdk/issues/51)).
- An unhandled Steam failure renders as the status it maps to rather than a blanket 500: 404 for `SteamUserNotFoundException` and `StatsUnavailableException`, 403 for `ProfileNotPublicException`, 429 with `Retry-After` for `SteamRateLimitException`, and 500 for a misconfiguration — never the status Steam answered a rejected key with. Set `steam-api.exceptions.render` to `false` to render the first three groups yourself; the 500 group is not configurable ([#37](https://github.com/fkrzski/laravel-steam-api-sdk/issues/37)).

### Changed

- **BC break.** The `{steamId}` route binding is opt-in — `steam-api.route_binding.enabled` defaults to `false`, in the shipped config and in the provider's fallback alike. Applications that relied on the old default have to set it to `true` ([#41](https://github.com/fkrzski/laravel-steam-api-sdk/issues/41)).
- **BC break.** `fkrzski/php-steam-api-sdk` `^0.4` is required, and it raises every HTTP failure as an SDK exception instead of a Saloon `RequestException` subclass. Code catching Saloon's `UnauthorizedException` for a private profile has to catch `ProfileNotPublicException` — or the root `SteamApiException` — instead ([#34](https://github.com/fkrzski/laravel-steam-api-sdk/issues/34)).
- **BC break.** `AsSteamId` implements `SerializesCastableAttributes`, so `toArray()` — and every API Resource built on it — emits the plain 64-bit string instead of the `SteamId` value object. Code reading `$model->toArray()['steam_id']` as an object has to read the attribute directly instead; `toJson()` already emitted the string, and now matches ([#39](https://github.com/fkrzski/laravel-steam-api-sdk/issues/39)).
- The daily rate-limit counter is keyed by API key rather than by connector class, so the budget `php artisan about` reports starts from a fresh count after the upgrade. Only the cached counter is orphaned — the quota Steam enforces is untouched.

### Fixed

- `AsSteamId` accepts an `int` or any `Stringable` on write, alongside a `string` and a `SteamId`, and rejects the rest with `InvalidSteamIdException` rather than a `TypeError` from inside the model. A JSON body sending the ID as an integer, and `$request->string()`, no longer need converting by hand ([#42](https://github.com/fkrzski/laravel-steam-api-sdk/issues/42)).

## [0.3.0] - 2026-08-15

### Added

- `Steam::friendList()` — wraps `GetFriendListRequest` and returns `list<Friend>`, optionally narrowed to a single `FriendRelationship`. A private friend list is a `401` from Steam, so the call throws Saloon's `UnauthorizedException` rather than returning an empty list.
- `php artisan about` — a "Steam API" section with the masked API key (last four characters only), the cache store backing the rate limit, and the remaining daily request budget. Values are resolved when the command renders, never on boot.
- `php artisan steam:install` — publishes `config/steam-api.php` and appends the prompted `STEAM_API_KEY` to `.env`. Safe to re-run: an existing config file is kept, and an existing key is only replaced once confirmed.

### Changed

- **BC break.** Every class the package ships is now `final`, so a subclass of `SteamManager`, `SteamIdRule`, `AsSteamId` or `SteamServiceProvider` no longer compiles. The documented extension points go through container rebinding — `SteamIdRouteBinding`, the `SteamConnector` binding — not inheritance.
- **BC break.** `fkrzski/php-steam-api-sdk` `^0.3` is required, and it groups its request classes into per-interface subnamespaces — `Http\Requests\ISteamUser`, `Http\Requests\ISteamUserStats` and `Http\Requests\IPlayerService`. The facade helpers are unaffected, but code that names a request class directly — a `Steam::fake()` response map, `assertSent()`, or a request passed to `send()` or `pool()` — has to update its imports.
- `SteamConnector` and `SteamManager` are bound as **scoped** instances rather than singletons, so both are rebuilt per request. A connector held beyond the request that resolved it is now stale — on a long-lived worker the shared one carried its API key into every later request.
- `Steam::fake()` throws `FakeOutsideTestsException` unless the application environment is `testing`. Nothing detaches the mock once it is attached.

### Fixed

- `Steam::fake()` no longer leaks its `MockClient` into later requests. The manager resolved the connector from the container captured at registration rather than the one that built it, so under Octane the mock stayed attached to a connector shared by the whole worker.
- A missing Steam Web API key throws `SteamApiKeyMissingException` naming both the `STEAM_API_KEY` env var and the `steam-api.key` config value, instead of surfacing a `TypeError` from `SteamConfig` inside the container. The check runs when the connector is first built rather than at boot, so an application without a key can still boot and publish the config.

## [0.2.0] - 2026-07-29

### Added

- `AsSteamId` Eloquent cast — converts a model attribute to a `SteamId` value object on read and serializes it back to its 64-bit string on write. Values are validated through `SteamId::fromSteamId64`; non-scalar stored values throw `InvalidSteamIdException` and `null` is preserved.
- `SteamId` route binding — a `{steamId}` route parameter is resolved into a `SteamId` value object through `SteamId::tryFromInput` (accepting a 64-bit ID or a `/profiles/<id>` URL), aborting with a 404 on unresolvable input. Enabled by default and configurable via `steam-api.route_binding` (`enabled`, `parameter`); the resolver `SteamIdRouteBinding` is resolved from the container and can be swapped by rebinding it.
- `SteamIdRule` validation rule — validates that an attribute resolves to a `SteamId`, accepting a 64-bit ID or a `/profiles/<id>` URL by default and only a raw 64-bit ID in `strict()` mode. Validation is format-only, and its messages ship as the `steam-api` translation namespace, publishable under the `steam-api-translations` tag.

## [0.1.0] - 2026-06-11

### Added

- `SteamServiceProvider` — auto-discovered; binds `SteamConnector` as a singleton (Octane-safe resolver) wired with the configured API key and the Laravel cache rate-limit store. Publishes `config/steam-api.php` under the `steam-api-config` tag.
- `SteamManager` — thin wrapper over `SteamConnector` exposing `connector()`, `send()`, `pool()` and convenience methods (`playerSummaries()`, `ownedGames()`, `userStatsForGame()`, `playerAchievements()`, `resolveVanityUrl()`).
- `Steam` facade for static access to the manager.
- `Steam::fake()` — attaches a Saloon `MockClient` to the singleton connector and returns it for assertions, removing per-test connector wiring.

[Unreleased]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.4.0...HEAD
[0.4.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/fkrzski/laravel-steam-api-sdk/releases/tag/0.1.0
