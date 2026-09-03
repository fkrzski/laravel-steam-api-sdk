<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Rendering;

use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeOutsideTestsException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLanguageException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\SteamApiKeyMissingException;
use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\TooManySteamIdsException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Turns an unhandled Steam failure into the HTTP response it deserves.
 *
 * Each method is registered as a render callback by {@see SteamServiceProvider},
 * which reflects on the parameter type to decide what it handles. Rebind this
 * class to swap the mapping; the provider resolves it from the container.
 *
 * Nothing is rendered here by hand — every method wraps the failure in the
 * matching {@see HttpException} and hands it back to Laravel's own handler, so
 * the application's error views, JSON negotiation and `Retry-After` header all
 * work exactly as they do for an `abort()`.
 */
final readonly class SteamExceptionRenderer
{
    /**
     * What the 500 group says instead of the exception message, matching what
     * Laravel puts in a JSON body for an unhandled failure. The messages name
     * our own configuration, and a rendered `HttpException` leaks its message
     * to the client even with debug off.
     */
    private const string SERVER_ERROR = 'Server Error';

    public function __construct(
        private ExceptionHandler $handler,
        private ConfigRepository $config,
    ) {}

    /**
     * No such user, or no stats for that game.
     *
     * Stats are ambiguous — the game exposes none, or the profile hides them —
     * so both answer 404 and neither says which.
     */
    public function notFound(SteamUserNotFoundException|StatsUnavailableException $e, Request $request): Response
    {
        return $this->handler->render($request, new NotFoundHttpException($e->getMessage(), $e));
    }

    public function forbidden(ProfileNotPublicException $e, Request $request): Response
    {
        return $this->handler->render($request, new AccessDeniedHttpException($e->getMessage(), $e));
    }

    /**
     * The daily quota is spent, so `Retry-After` carries what is left of the
     * window.
     *
     * The floor is a second rather than zero: a window that already closed
     * counts down past it, and Symfony drops the header entirely for a falsy
     * value — leaving the client nothing to back off on.
     */
    public function rateLimited(SteamRateLimitException $e, Request $request): Response
    {
        return $this->handler->render($request, new TooManyRequestsHttpException(
            max(1, $e->limit->getRemainingSeconds()),
            $e->getMessage(),
            $e,
        ));
    }

    /**
     * A failure the client cannot do anything about.
     *
     * Steam answers a bad key with 400 or 403, and passing that on would blame
     * the caller for our misconfiguration — so this group is a 500 whatever the
     * response said, and whatever `steam-api.exceptions.render` is set to.
     *
     * Debug builds fall through instead: the status is a 500 either way, and
     * the developer keeps the exception page naming the config value to fix.
     */
    public function misconfigured(
        InvalidApiKeyException|TooManySteamIdsException|SteamApiKeyMissingException
        |InvalidSteamLanguageException|FakeOutsideTestsException $e,
        Request $request,
    ): ?Response {
        if ($this->config->get('app.debug') === true) {
            return null;
        }

        return $this->handler->render($request, new HttpException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SERVER_ERROR,
            $e,
        ));
    }
}
