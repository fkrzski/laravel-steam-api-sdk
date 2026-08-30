<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Contracts;

use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves a route parameter into a {@see SteamId} value object.
 *
 * Bind this interface to swap the resolver — the shipped class is `final`, so a
 * replacement wraps it rather than extends it.
 */
interface SteamIdBinder
{
    /**
     * @throws NotFoundHttpException when the value is not a resolvable Steam ID
     */
    public function __invoke(string $value): SteamId;
}
