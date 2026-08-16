<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Routing;

use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves a route parameter into a {@see SteamId} value object.
 *
 * Registered as the binder for the configured route parameter by
 * {@see SteamServiceProvider}, which resolves it from the container per request.
 * Rebind this class to swap the behaviour: a replacement must be invokable, take
 * a string, return a {@see SteamId} and throw on input it cannot resolve.
 * Resolution is format-only, so a vanity name needs an API call to become one.
 */
final class SteamIdRouteBinding
{
    /**
     * @throws NotFoundHttpException when the value is not a resolvable Steam ID
     */
    public function __invoke(string $value): SteamId
    {
        return SteamId::tryFromInput($value)
            ?? throw new NotFoundHttpException(sprintf('"%s" is not a resolvable Steam ID.', $value));
    }
}
