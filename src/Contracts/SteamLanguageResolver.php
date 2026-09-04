<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Contracts;

use Fkrzski\SteamApiSdk\Enums\Language;

/**
 * Resolves an application locale into one of Steam's own language codes.
 *
 * Bind this interface to swap the table — the shipped resolver is `final`, so a
 * replacement wraps it rather than extends it.
 */
interface SteamLanguageResolver
{
    /**
     * Null when nothing matches: a missing translation is not a misconfiguration,
     * and the endpoints answer fine without `l`.
     */
    public function __invoke(string $locale): ?Language;
}
