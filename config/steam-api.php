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
    | Timeouts & Retries
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
    */

    'http' => [
        'connect_timeout' => env('STEAM_API_CONNECT_TIMEOUT'),
        'request_timeout' => env('STEAM_API_REQUEST_TIMEOUT'),

        'retry' => [
            'tries' => env('STEAM_API_RETRY_TRIES', 1),
            'interval' => env('STEAM_API_RETRY_INTERVAL', 0),
            'exponential_backoff' => env('STEAM_API_RETRY_EXPONENTIAL_BACKOFF', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Store
    |--------------------------------------------------------------------------
    |
    | The cache store holding the daily request counter and the wait after a
    | 429. Left unset it is the default store, which `cache:clear` flushes —
    | resetting the count while Steam's own carries on. Name a store from
    | config/cache.php to keep it apart; one that evicts keys can drop it too.
    |
    */

    'rate_limit' => [
        'store' => env('STEAM_API_RATE_LIMIT_STORE'),
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
    | private profile a 403, a spent daily quota a 429 carrying Retry-After. Set
    | this to false to render them yourself. Misconfiguration — a rejected API
    | key, an oversized batch — is always a 500, so Steam's own status never
    | reaches your client.
    |
    */

    'exceptions' => [
        'render' => true,
    ],

];
