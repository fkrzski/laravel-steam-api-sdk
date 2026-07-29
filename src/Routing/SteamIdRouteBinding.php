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
 * {@see SteamServiceProvider}. Swap the behaviour by
 * rebinding this class in the container.
 */
class SteamIdRouteBinding
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
