<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when the configured default language is not a code Steam accepts.
 *
 * Raised lazily, the first time the connector is resolved, so a rejected code
 * surfaces on the first Steam call rather than taking the application down at
 * boot — the same bargain {@see SteamApiKeyMissingException} strikes.
 */
final class InvalidSteamLanguageException extends SteamApiException
{
    public function __construct(string $language)
    {
        parent::__construct(sprintf(
            'The configured Steam language "%s" is not one Steam accepts. Set STEAM_API_LANGUAGE in your .env '
            .'file, or the "steam-api.language" config value, to a code from %s — Valve spells them its own way, '
            .'so "koreana" and "brazilian" rather than the ISO codes.',
            $language,
            Language::class,
        ));
    }
}
