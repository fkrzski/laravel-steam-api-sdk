<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Steam Web API Key
    |--------------------------------------------------------------------------
    |
    | Your Steam Web API key, obtained from https://steamcommunity.com/dev.
    | It is sent as the "key" query parameter on every request the connector
    | makes to https://api.steampowered.com.
    |
    */

    'key' => env('STEAM_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | Steam's own language code, sent on every request whose payload is localised
    | — achievement names and descriptions among them. The codes are Valve's
    | rather than ISO ones, so "koreana" and "brazilian"; see the Language enum
    | for the full list, because anything outside it is a configuration error.
    |
    | Left unset, the language follows the application's locale through the
    | SteamLanguageResolver contract, which you can rebind. An empty string — or
    | a locale Steam has no language for — sends none, leaving the choice to Steam.
    |
    */

    'language' => env('STEAM_API_LANGUAGE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | How long the connector waits on Steam, in seconds — to connect, then for
    | the whole request. Left unset, Saloon's 10 and 30 apply; 0 waits forever.
    |
    | Only a request Steam never answered, or answered with a 5xx, is sent
    | again — up to `tries` attempts in all, `interval` milliseconds apart,
    | doubled after each retry with exponential backoff. A 4xx or a spent quota
    | never is, because every attempt spends a request from the daily budget.
    | One attempt is the default, and requests sent through a pool go out once.
    |
    | Requests go out through Guzzle unless `sender` names a class implementing
    | Saloon's Sender. It is resolved from the container each time the
    | connector is built, so a binding decides how: a singleton is shared, an
    | unbound class is built anew. To keep Guzzle with middleware of your own,
    | bind a GuzzleSender with it attached and name that. A sender on another
    | client has to read Saloon's `connect_timeout` and `timeout` options off
    | the request, which is how the timeouts above reach it.
    |
    */

    'http' => [
        'connect_timeout' => env('STEAM_API_CONNECT_TIMEOUT'),
        'request_timeout' => env('STEAM_API_REQUEST_TIMEOUT'),

        'retry' => [
            'tries' => env('STEAM_API_RETRY_TRIES', 1),
            'interval' => env('STEAM_API_RETRY_INTERVAL', 0),
            'exponential_backoff' => env('STEAM_API_RETRY_EXPONENTIAL_BACKOFF', false),
        ],

        'sender' => env('STEAM_API_SENDER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | The cache store holding the request counters and the wait after a 429.
    | Left unset it is the default store, which `cache:clear` flushes —
    | resetting the count while Steam's own carries on. Name a store from
    | config/cache.php to keep it apart; one that evicts keys can drop it too.
    |
    | Left null, `limits` keeps Steam's 100 000 requests a day, reset at 00:00
    | UTC and counted only with an API key. A list replaces that, key or not:
    | [] counts nothing locally, and each entry allows `allow` requests every
    | `every` seconds — or every 'day', up to 00:00 UTC — waiting the window
    | out instead of throwing when `sleep` is true. Changing an entry's `allow`
    | or `every` restarts its count.
    |
    |     ['allow' => 100_000, 'every' => 'day'],
    |     ['allow' => 10, 'every' => 1, 'sleep' => true],
    |
    | A 429 from Steam without a Retry-After holds requests back for `cooldown`
    | seconds.
    |
    */

    'rate_limit' => [
        'store' => env('STEAM_API_RATE_LIMIT_STORE'),

        'limits' => null,

        'cooldown' => env('STEAM_API_RATE_LIMIT_COOLDOWN', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Model Binding
    |--------------------------------------------------------------------------
    |
    | Resolve a route parameter into a SteamId value object through
    | SteamId::tryFromInput() — a 64-bit ID or a profile URL becomes a SteamId,
    | anything else aborts with a 404. Opt-in: the binding is registered under a
    | global parameter name, so the only routes it can claim are yours.
    |
    */

    'route_binding' => [
        'enabled' => false,
        'parameter' => 'steamId',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Rendering
    |--------------------------------------------------------------------------
    |
    | Turn an unhandled Steam failure into a sensible HTTP response instead of a
    | blanket 500: a missing user or an app ID Steam does not know is a 404, a
    | private profile a 403, a rejected server address a 422, a spent daily
    | quota a 429 carrying Retry-After, and Steam down or unreachable a 503.
    | Set this to false to render them yourself. Misconfiguration — a rejected
    | API key, an oversized batch — is always a 500, so Steam's own status
    | never reaches your client.
    |
    */

    'exceptions' => [
        'render' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Name a channel from config/logging.php to log every failed Steam call to
    | it as a warning; left unset, nothing is logged. Turn on `responses` to log
    | every response Steam sends as well, at debug. The API key never reaches
    | the log, but a logged query does carry the Steam IDs it asked about.
    |
    */

    'logging' => [
        'channel' => env('STEAM_API_LOG_CHANNEL'),
        'responses' => env('STEAM_API_LOG_RESPONSES', false),
    ],

];
