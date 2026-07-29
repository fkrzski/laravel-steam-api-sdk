<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Steam ID Validation Messages
    |--------------------------------------------------------------------------
    |
    | Used by the SteamIdRule validation rule. "steam_id" covers the default
    | mode, which accepts a 64-bit ID or a profile URL, while "steam_id_strict"
    | covers the rule's strict() mode, which accepts a 64-bit ID only.
    |
    */

    'steam_id' => 'The :attribute field must be a valid Steam ID or profile URL.',
    'steam_id_strict' => 'The :attribute field must be a valid 64-bit Steam ID.',

];
