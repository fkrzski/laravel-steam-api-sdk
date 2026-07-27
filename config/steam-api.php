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
    | Bind a route parameter to a SteamId value object. When enabled, any route
    | segment matching the configured parameter name is resolved through
    | SteamId::tryFromInput() — a 64-bit ID or a profile URL becomes a SteamId,
    | while an unresolvable value aborts with a 404. Set "enabled" to false to
    | leave routing untouched, or change "parameter" to claim a different name.
    |
    */

    'route_binding' => [
        'enabled' => true,
        'parameter' => 'steamId',
    ],

];
