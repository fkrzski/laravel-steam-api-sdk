# Changelog

All notable changes to `laravel-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `AsSteamId` Eloquent cast — converts a model attribute to a `SteamId` value object on read and serializes it back to its 64-bit string on write. Values are validated through `SteamId::fromSteamId64`; non-scalar stored values throw `InvalidSteamIdException` and `null` is preserved.

## [0.1.0] - 2026-06-011

Initial release. Laravel bridge for [`fkrzski/php-steam-api-sdk`](https://github.com/fkrzski/php-steam-api-sdk).

### Added

- `SteamServiceProvider` — auto-discovered; binds `SteamConnector` as a singleton (Octane-safe resolver) wired with the configured API key and the Laravel cache rate-limit store. Publishes `config/steam-api.php` under the `steam-api-config` tag.
- `SteamManager` — thin wrapper over `SteamConnector` exposing `connector()`, `send()`, `pool()` and convenience methods (`playerSummaries()`, `ownedGames()`, `userStatsForGame()`, `playerAchievements()`, `resolveVanityUrl()`).
- `Steam` facade for static access to the manager.
- `Steam::fake()` — attaches a Saloon `MockClient` to the singleton connector and returns it for assertions, removing per-test connector wiring.

[Unreleased]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.1.0...HEAD
[0.1.0]: https://github.com/fkrzski/laravel-steam-api-sdk/releases/tag/0.1.0
