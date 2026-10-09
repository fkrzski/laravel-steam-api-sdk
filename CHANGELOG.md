# Changelog

All notable changes to `laravel-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Steam::news()` — the base SDK's `NewsResource`, handed back the same way as `players()`, `users()`, `stats()` and `apps()`. Steam serves its one endpoint anonymously, so it needs no `STEAM_API_KEY` ([#105](https://github.com/fkrzski/laravel-steam-api-sdk/issues/105)).
- `Steam::appNews()` — wraps `GetNewsForAppRequest` and returns `AppNews`, an app's posts newest first, whose `total` counts every post the filter matches rather than the page. An app ID Steam does not know and some apps it does, such as Spacewar, both raise `AppNewsUnavailableException`, because Steam answers the two alike ([#105](https://github.com/fkrzski/laravel-steam-api-sdk/issues/105)).
- `AppNewsFactory` and `NewsItemFactory`, with `SteamResponse::appNews()` for the `appnews` envelope. `->total()` keeps Steam's count apart from the items listed, so a test can fake page one of many, and `->withoutAuthor()` sends the `''` Steam sends for a post without a byline, which the DTO reads back as `null` ([#105](https://github.com/fkrzski/laravel-steam-api-sdk/issues/105)).
- `SteamResponse::appNewsUnavailable()` — the 403 with an empty JSON object that `GetNewsForAppRequest` raises as `AppNewsUnavailableException`, faked fluently as `->news()->appNewsUnavailable()` beside `->news()->appNews()` on `SteamFake` ([#105](https://github.com/fkrzski/laravel-steam-api-sdk/issues/105)).
- `Steam::newsFeed()` — an app's news as a `LazyCollection` of `NewsItem`, one `GetNewsForAppRequest` per page of `perPage` (20), skipping by ID the items each inclusive `enddate` repeats, so `->take(30)` stops sending once it has enough. It ends on a page holding all that remains or bringing nothing new, which cuts it short when `perPage` or more items share one second, and a `perPage` below 2 raises `InvalidArgumentException` ([#109](https://github.com/fkrzski/laravel-steam-api-sdk/issues/109)).
- `SteamResponse::newsFeed()` — a whole feed of `NewsItemFactory` items, answering each `GetNewsForAppRequest` with the page Steam would send for its `count` and `enddate`, faked fluently as `->news()->newsFeed()` on `SteamFake`. `feeds`, `tags` and `maxlength` are not applied ([#109](https://github.com/fkrzski/laravel-steam-api-sdk/issues/109)).
- `Events\SteamRequestSending` and `Events\SteamResponseReceived`, dispatched for every attempt with the Steam `method`, the key-free `query` and the `attempt`, the second adding `status` and `duration` in seconds, and `Events\SteamRequestFailed`, dispatched once per failed call with the `exception` the caller gets. Listeners sit on the dispatcher, so they outlive the scoped connector and `Event::fake()` sees them under `Steam::fake()`, but that exception does not serialize, so a `SteamRequestFailed` listener cannot be queued ([#107](https://github.com/fkrzski/laravel-steam-api-sdk/issues/107)).
- `steam-api.logging` — off by default, `channel` (`STEAM_API_LOG_CHANNEL`) logs every failed Steam call there as a warning with the exception's class and code in context, and `responses` (`STEAM_API_LOG_RESPONSES`) adds every response at debug with its query, Steam IDs included, in context. Both run as listeners on the traffic events, so `Event::fake()` silences them, while a channel `logging.channels` does not define or a `responses` that is not a boolean raises `InvalidSteamLoggingOptionException`, a 500 like `InvalidSteamRateLimitStoreException` ([#108](https://github.com/fkrzski/laravel-steam-api-sdk/issues/108)).
- `php artisan about` — a "Logging" row reading `disabled`, the channel name, with `(+ responses)` once responses are logged too, or `INVALID` for a rejected option ([#108](https://github.com/fkrzski/laravel-steam-api-sdk/issues/108)).

### Changed

- **BC break.** `fkrzski/php-steam-api-sdk` `^0.8` is required, and every message built from a Steam response opens with the Steam method, rendered bodies included, so an unknown app's 404 reads `GetNumberOfCurrentPlayers: No Steam app found for app ID 1.` A client matching on `message` has to match the new text, while the 429, 500 and 503 bodies stay as they were ([#104](https://github.com/fkrzski/laravel-steam-api-sdk/issues/104)).
- **BC break.** `Steam::send()` and `Steam::pool()` raise the failure Steam reports in a 200 payload inside the call, so `Steam::send(new GetOwnedGamesRequest(...))` for a private profile throws there rather than on `->dto()`, and a pool rejects where it used to fulfil. The helpers and the resources throw what they did before ([#104](https://github.com/fkrzski/laravel-steam-api-sdk/issues/104)).
- **BC break.** `getPrevious()` is null on `SteamConnectionException` and on a `SteamApiException` raised from a 4xx or 5xx, so a `report()` callback walking the chain for Saloon's exception finds nothing. Read the status from `getCode()` and the payload from `response` instead ([#104](https://github.com/fkrzski/laravel-steam-api-sdk/issues/104)).
- **BC break.** `ProfileNotPublicException::forSteamId()`, `ProfileNotPublicException::forPrivateOrMissing()` and `SteamUserNotFoundException::forVanity()` require the Saloon `Response`, so code building them by hand has to pass one ([#104](https://github.com/fkrzski/laravel-steam-api-sdk/issues/104)).
- `AppNewsUnavailableException` renders as a 404 rather than a blanket 500, and not as the 403 Steam answered with, since the client asked for nothing forbidden. It joins the client-facing group under `steam-api.exceptions.render`, so an application rendering it itself is unaffected ([#106](https://github.com/fkrzski/laravel-steam-api-sdk/issues/106)).

### Fixed

- `Steam::connector()->debug()` and `SteamConnectionException` mask the API key as `key=***`, and `Steam::pool()` counts a 4xx or 5xx against the daily budget and raises a 429 as `SteamRateLimitException`, the same as a helper call ([#104](https://github.com/fkrzski/laravel-steam-api-sdk/issues/104)).
- `Steam::fake()` types a callable response as taking the `PendingRequest` Saloon passes it, so a closure reading the request no longer fails static analysis ([#109](https://github.com/fkrzski/laravel-steam-api-sdk/issues/109)).

## [0.7.0] - 2026-10-06

### Added

- `Steam::apps()` — the base SDK's `AppsResource`, handed back the same way as `players()`, `users()` and `stats()`. Steam serves every ISteamApps endpoint anonymously, so none of them needs `STEAM_API_KEY` ([#86](https://github.com/fkrzski/laravel-steam-api-sdk/issues/86)).
- `Steam::upToDateCheck()` — wraps `UpToDateCheckRequest` and returns `AppVersionCheck`, telling a game server whether its version is current. An app ID Steam does not know and an app running no versioned servers both raise `AppVersionUnavailableException`, because Steam answers the two alike ([#86](https://github.com/fkrzski/laravel-steam-api-sdk/issues/86)).
- `Steam::serversAtAddress()` — wraps `GetServersAtAddressRequest` and returns `list<GameServer>`, the game servers at an IP address. An address Steam rejects raises `InvalidServerAddressException` ([#86](https://github.com/fkrzski/laravel-steam-api-sdk/issues/86)).
- `AppVersionCheckFactory` and `GameServerFactory`, with `SteamResponse::upToDateCheck()` and `SteamResponse::serversAtAddress()` for their `response` envelopes. `->withoutSpectatorPort()` sends the `0` Steam sends for a server without SourceTV, which the DTO reads back as `null` ([#86](https://github.com/fkrzski/laravel-steam-api-sdk/issues/86)).
- `SteamResponse::appVersionUnavailable()`, `SteamResponse::invalidServerAddress()` and `SteamResponse::serversAtAddressRefused()` — the three failures the new endpoints answer with 200 and `success: false`. The last is the per-IP refusal the base SDK raises as the root `SteamApiException`, so nothing but the builder names it ([#86](https://github.com/fkrzski/laravel-steam-api-sdk/issues/86)).
- `Steam::sdrConfig()` — wraps `GetSdrConfigRequest` and returns `SdrConfig`, the Steam Datagram Relay points of presence a game connects through, keyed by code. An app ID Steam does not know raises `AppNotFoundException` on the first attempt, however many tries `steam-api.http.retry.tries` allows ([#89](https://github.com/fkrzski/laravel-steam-api-sdk/issues/89)).
- `SdrConfigFactory`, `SdrPointOfPresenceFactory` and `SdrRelayFactory`, with `SteamResponse::sdrConfig()` for the top-level payload. A point of presence carries its code into the key of `pops` rather than the payload, `->latitude()` and `->longitude()` write `geo` in Valve's `[longitude, latitude]` order, and `->withoutRelays()` drops the key the way Steam does ([#89](https://github.com/fkrzski/laravel-steam-api-sdk/issues/89)).
- `SteamResponse::sdrConfigAppNotFound()` — the 500 carrying `Failed to get appinfo` that `GetSdrConfigRequest` raises as `AppNotFoundException`, a third unknown-app shape beside `appNotFound()` and `schemaAppNotFound()` ([#89](https://github.com/fkrzski/laravel-steam-api-sdk/issues/89)).
- `steam-api.http` — `connect_timeout` and `request_timeout` in seconds, and `retry.tries`, `retry.interval` in milliseconds and `retry.exponential_backoff`, each read from its own `STEAM_API_*` variable and handed to the base SDK's `SteamConfig` when the connector is built. Left unset they keep Saloon's 10 and 30 seconds and a single attempt, while a value that is not a number or is out of range raises `InvalidSteamHttpOptionException` naming the key — a misconfiguration, so it renders as a 500 whatever `steam-api.exceptions.render` says ([#87](https://github.com/fkrzski/laravel-steam-api-sdk/issues/87)).
- `php artisan about` — "Timeouts" and "Retries" rows reporting what the connector is built with, marked `(default)` until the `steam-api.http` block changes them. A rejected value reads `INVALID` in its own row, and the daily budget `UNKNOWN` whenever any row does, since the connector cannot be built ([#87](https://github.com/fkrzski/laravel-steam-api-sdk/issues/87)).
- `Steam::fake()` drops the pause between attempts but keeps the tries, so a faked 5xx is retried at once and `Steam::assertSentCount()` still counts every attempt. Saloon pauses with a bare `usleep()`, which `Sleep::fake()` cannot reach ([#87](https://github.com/fkrzski/laravel-steam-api-sdk/issues/87)).
- `Queue\Middleware\RespectsSteamRateLimit` — job middleware for a job's own `middleware()`, releasing it for what is left of the window when the daily budget is already spent, before the job runs, or when a request inside it raises `SteamRateLimitException`; without an API key only a 429 from Steam holds it back. A release counts as an attempt, so a job waiting out a daily window wants `retryUntil()` rather than a small `$tries` ([#88](https://github.com/fkrzski/laravel-steam-api-sdk/issues/88)).
- `steam-api.rate_limit.store` — the cache store the rate limit counter is kept in, read from `STEAM_API_RATE_LIMIT_STORE`, so a `cache:clear` of the default store no longer resets the daily budget while Steam's own count carries on. Left unset it stays the default store, and a name `cache.stores` does not define raises `InvalidSteamRateLimitStoreException`, a 500 like `InvalidSteamHttpOptionException` ([#96](https://github.com/fkrzski/laravel-steam-api-sdk/issues/96)).
- `SteamResponse::connectionFailed()` — a request that never reaches Steam, retried like a real outage and raised as `SteamConnectionException` once the tries are spent. Saloon throws it before recording a response, so `Steam::recorded()` and the `assertSent` family never see the attempt ([#90](https://github.com/fkrzski/laravel-steam-api-sdk/issues/90)).
- `Steam::fake()->users()->summaries(...)` and the rest of the four resources' endpoints on the returned `SteamFake`, each registering its `SteamResponse` builder under the request class it sends and handing the mock back, so a chain moves resource by resource and still ends on something the assertions read. A second call to the same endpoint replaces the first ([#91](https://github.com/fkrzski/laravel-steam-api-sdk/issues/91)).
- Named failures beside each endpoint — `->users()->friendsNotPublic()`, `->stats()->schemaAppNotFound()` and the rest — each faking what Steam answers that endpoint with, so the test gets the exception the base SDK raises for it. `->invalidApiKey()` and `->connectionFailed()` on `SteamFake` answer every request without a response of its own ([#91](https://github.com/fkrzski/laravel-steam-api-sdk/issues/91)).

### Changed

- **BC break.** `fkrzski/php-steam-api-sdk` `^0.7` is required, and a transport failure surfaces as its `SteamConnectionException` rather than Saloon's `FatalRequestException`. Code catching the Saloon exception has to catch the SDK one instead ([#85](https://github.com/fkrzski/laravel-steam-api-sdk/issues/85)).
- `php artisan about` reads `not metered (no API key)` for the daily budget with no key configured, where it counted down from 100 000 before. Steam bills the quota to the key, so a connector built without one meters nothing ([#85](https://github.com/fkrzski/laravel-steam-api-sdk/issues/85)).
- `php artisan about` names the configured store in its "Rate Limit Store" row and marks the default one `(default)`, where it always read `cache.default` before. A store the cache config does not define reads `INVALID` ([#96](https://github.com/fkrzski/laravel-steam-api-sdk/issues/96)).
- `SteamConnectionException`, and the root `SteamApiException` raised for a 5xx once the retries are spent, render as a 503 rather than a blanket 500, its body saying `Service Unavailable` because base 0.7.0 quotes the request URI, API key included, in the message ([#90](https://github.com/fkrzski/laravel-steam-api-sdk/issues/90)).
- `AppVersionUnavailableException` renders as a 404 and `InvalidServerAddressException` as a 422, rather than a blanket 500. Both join the 503 in the client-facing group under `steam-api.exceptions.render`, so an application rendering these itself is unaffected ([#90](https://github.com/fkrzski/laravel-steam-api-sdk/issues/90)).
- **BC break.** `Contracts\SteamManager::fake()` returns `Testing\SteamFake`, a subclass of Saloon's `MockClient`, so a class implementing the contract itself has to narrow its return type. The array form and every assertion on the returned mock work as before ([#91](https://github.com/fkrzski/laravel-steam-api-sdk/issues/91)).

### Removed

- **BC break.** `SteamApiKeyMissingException` and `Http\RequiresConfiguredApiKey` — the base SDK refuses a request that needs a key before it goes out, raising `ApiKeyNotConfiguredException`, which renders as a 500 the same way. Code catching the bridge exception has to catch the base one instead ([#85](https://github.com/fkrzski/laravel-steam-api-sdk/issues/85)).

## [0.6.0] - 2026-09-04

### Added

- `Steam::currentPlayers()` — wraps `GetNumberOfCurrentPlayersRequest` and returns the concurrent player count as a plain `int` rather than a DTO. An app ID Steam does not know raises `AppNotFoundException` ([#70](https://github.com/fkrzski/laravel-steam-api-sdk/issues/70)).
- `Steam::globalAchievements()` — wraps `GetGlobalAchievementPercentagesForAppRequest` and returns `list<GlobalAchievement>`, one entry per achievement with the share of the player base that has it. Its argument is `$gameId`, the name the base SDK keeps because Valve spells this one endpoint `gameid` ([#70](https://github.com/fkrzski/laravel-steam-api-sdk/issues/70)).
- `GlobalAchievementFactory`, with `SteamResponse::currentPlayers()` and `SteamResponse::globalAchievements()` for their envelopes. The factory holds the percentage as the string Steam sends, so the cast to a float stays the DTO's ([#70](https://github.com/fkrzski/laravel-steam-api-sdk/issues/70)).
- `SteamResponse::appNotFound()` and `SteamResponse::globalAchievementsRefused()` — the two failure shapes the new endpoints answer with, a 404 carrying `result: 42` and a 403 carrying an empty JSON object. Neither could be built from the failure builders already here ([#70](https://github.com/fkrzski/laravel-steam-api-sdk/issues/70)).
- `Steam::schema()` — wraps `GetSchemaForGameRequest` and returns `GameSchema`, every stat and achievement a game publishes. An app publishing no schema answers with an empty DTO rather than a failure, while an app ID Steam does not know raises `AppNotFoundException` ([#71](https://github.com/fkrzski/laravel-steam-api-sdk/issues/71)).
- `GameSchemaFactory`, `SchemaStatFactory` and `SchemaAchievementFactory`, with `SteamResponse::gameSchema()` for the `game` envelope. `->empty()` builds what an app publishing no schema answers with, dropping the name and version along with both lists ([#71](https://github.com/fkrzski/laravel-steam-api-sdk/issues/71)).
- `SteamResponse::schemaAppNotFound()` — the bare 400 `GetSchemaForGameRequest` raises `AppNotFoundException` from. It returns byte for byte what `SteamResponse::statsRefused()` does, and keeps its own name because nothing but the endpoint says which failure a test is faking ([#71](https://github.com/fkrzski/laravel-steam-api-sdk/issues/71)).
- `steam-api.language` — the default language every localised request carries, read from `STEAM_API_LANGUAGE` and validated against the base SDK's `Language` enum when the connector is built. It ships unset and an empty string sends no language at all, while a code Steam does not know raises `InvalidSteamLanguageException` — a misconfiguration, so it renders as a 500 whatever `steam-api.exceptions.render` says ([#72](https://github.com/fkrzski/laravel-steam-api-sdk/issues/72)).
- `php artisan about` — a "Language" row reporting the code every localised request carries and which of the two answers it is, `polish (config)` or `polish (locale: pl)`. `NOT SET` names its source the same way, while `INVALID` stands alone, since only config can carry a rejected code ([#72](https://github.com/fkrzski/laravel-steam-api-sdk/issues/72), [#73](https://github.com/fkrzski/laravel-steam-api-sdk/issues/73)).
- `Contracts\SteamLanguageResolver`, bound to `Localization\LocaleLanguageResolver` — with `steam-api.language` unset the language follows `app()->getLocale()` through a table of Valve's own codes, so `pl` is `polish` while `ko` is `koreana` and `pt_BR` is `brazilian`. A locale Steam publishes no language for sends none rather than raising, and an application with its own locale scheme rebinds the contract instead of forking the map ([#73](https://github.com/fkrzski/laravel-steam-api-sdk/issues/73)).
- `AppNotFoundException` renders as a 404 — an app ID Steam does not know is a missing resource rather than a server fault, and `Steam::currentPlayers()` and `Steam::schema()` both raise it. It joins the client-facing group under `steam-api.exceptions.render`, so an application rendering these itself is unaffected ([#74](https://github.com/fkrzski/laravel-steam-api-sdk/issues/74)).

### Changed

- **BC break.** `fkrzski/php-steam-api-sdk` `^0.6` is required, and the `$language` argument on `Steam::userStats()` and `Steam::achievements()` is a `Language` enum rather than a string. A call passing `'english'` swaps it for `Language::English` ([#69](https://github.com/fkrzski/laravel-steam-api-sdk/issues/69)).
- `php artisan about` reports the daily request budget with no key configured, where it read `UNKNOWN` before. The counter hangs off the connector, which is now built without a key, and anonymous requests spend it like any other ([#75](https://github.com/fkrzski/laravel-steam-api-sdk/issues/75)).

### Fixed

- `Steam::currentPlayers()` and `Steam::globalAchievements()` no longer need `STEAM_API_KEY` set, because the key is checked when a request that needs one is sent rather than while the connector is built. Steam serves both endpoints anonymously, and a request that does need a key still fails before it goes out, with `SteamApiKeyMissingException` naming the config value to set ([#75](https://github.com/fkrzski/laravel-steam-api-sdk/issues/75)).

## [0.5.0] - 2026-08-30

### Added

- `Steam::recentlyPlayedGames()` — wraps `GetRecentlyPlayedGamesRequest` and returns `RecentlyPlayedGames`. Its `$totalCount` is Steam's own total for the two-week window, so it can outrun the games the payload lists ([#56](https://github.com/fkrzski/laravel-steam-api-sdk/issues/56)).
- `Steam::steamLevel()` — wraps `GetSteamLevelRequest` and returns the community level as a plain `int` rather than a DTO ([#56](https://github.com/fkrzski/laravel-steam-api-sdk/issues/56)).
- `RecentlyPlayedGamesFactory` and `RecentlyPlayedGameFactory`, with `SteamResponse::recentlyPlayedGames()` and `SteamResponse::steamLevel()` for their envelopes. `->totalCount()` sets the total apart from the games listed, `->nothingPlayed()` builds the window a player played nothing in ([#56](https://github.com/fkrzski/laravel-steam-api-sdk/issues/56)).
- `Steam::badges()` — wraps `GetBadgesRequest` and returns `PlayerBadges`, the badges a player has earned alongside the level and XP they add up to. A hidden profile raises `ProfileNotPublicException` rather than returning an empty list ([#57](https://github.com/fkrzski/laravel-steam-api-sdk/issues/57)).
- `Steam::communityBadgeProgress()` — wraps `GetCommunityBadgeProgressRequest` and returns `list<CommunityBadgeQuest>`, one entry per quest with the flag saying whether it is done ([#57](https://github.com/fkrzski/laravel-steam-api-sdk/issues/57)).
- `BadgeFactory`, `PlayerBadgesFactory` and `CommunityBadgeQuestFactory`, with `SteamResponse::badges()` and `SteamResponse::communityBadgeProgress()` for the envelopes. Two named states cover what the badge payload does that no other does: `->communityItem()` for the id Steam sends as a string, `->withoutApp()` for a badge tied to no game ([#57](https://github.com/fkrzski/laravel-steam-api-sdk/issues/57)).
- `SteamResponse::apiKeyUnauthorized()` — the same rejected key `invalidApiKey()` fakes, answered with 401 instead of 403. `GetCommunityBadgeProgress` picks that status, so without this builder the mapping to `InvalidApiKeyException` had nothing to test against ([#57](https://github.com/fkrzski/laravel-steam-api-sdk/issues/57)).
- `Steam::players()`, `Steam::users()` and `Steam::stats()` — the base SDK's fluent resources, handed back exactly as the connector builds them. A resource reached this way sends through the connector `Steam::fake()` attached its mock to, so one fake covers both the fluent and the flat surface ([#58](https://github.com/fkrzski/laravel-steam-api-sdk/issues/58)).
- `Contracts\SteamManager` and `Contracts\SteamIdBinder` — the interfaces the `Steam` facade and the route binding resolve, so injecting or swapping either piece means binding an interface rather than type-hinting a `final` class. The concrete classes stay bound under their own names, so an application that already rebound `SteamIdRouteBinding` keeps winning ([#59](https://github.com/fkrzski/laravel-steam-api-sdk/issues/59)).

### Changed

- **BC break.** `fkrzski/php-steam-api-sdk` `^0.5` is required, and `PlayerSummary::$timeCreated` is nullable there because Steam omits the creation date on a hidden profile. Code reading the date off `Steam::summaries()` needs a null check ([#55](https://github.com/fkrzski/laravel-steam-api-sdk/issues/55)).
- **BC break.** `SteamResponse::ownedGamesNotPublic()` is now `SteamResponse::playerServiceNotPublic()`, because every IPlayerService endpoint refuses a hidden profile with the same empty `response` object. Tests calling the old name have to rename ([#56](https://github.com/fkrzski/laravel-steam-api-sdk/issues/56)).
- **BC break.** Six facade helpers carry the names of the resource methods they now delegate to: `playerSummaries()` is `summaries()`, `playerBans()` is `bans()`, `friendList()` is `friends()`, `userGroupList()` is `groups()`, `userStatsForGame()` is `userStats()` and `playerAchievements()` is `achievements()`. Calling code has to rename; the `SteamResponse` builders keep their own names, which follow the request classes they fake rather than the helpers ([#58](https://github.com/fkrzski/laravel-steam-api-sdk/issues/58)).

### Fixed

- `PlayerSummaryFactory::private()` drops `timecreated` along with the rest of the details Steam withholds, so a faked hidden profile carries a null `timeCreated` the way the real response does. A test asserting the faked timestamp has to drop that assertion ([#60](https://github.com/fkrzski/laravel-steam-api-sdk/issues/60)).

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

[Unreleased]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.7.0...HEAD
[0.7.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/fkrzski/laravel-steam-api-sdk/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/fkrzski/laravel-steam-api-sdk/releases/tag/0.1.0
