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
    | blanket 500: a missing user is a 404, a private profile a 403, a spent
    | daily quota a 429 carrying Retry-After. Set this to false to render them
    | yourself. Misconfiguration — a rejected API key, an oversized batch — is
    | always a 500, so Steam's own status never reaches your client.
    |
    */

    'exceptions' => [
        'render' => true,
    ],

];
