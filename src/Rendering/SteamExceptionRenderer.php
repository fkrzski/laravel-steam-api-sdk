<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Rendering;

use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeOutsideTestsException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamHttpOptionException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLanguageException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLoggingOptionException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamRateLimitStoreException;
use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
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
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

    /**
     * What a 503 says instead of the exception message: the base SDK quotes
     * Guzzle's reason for a failed connection and the request URI, masking only
     * the API key in it.
     */
    private const string SERVICE_UNAVAILABLE = 'Service Unavailable';

    public function __construct(
        private ExceptionHandler $handler,
        private ConfigRepository $config,
    ) {}

    /**
     * No such user, no such app, no stats for that game, no server version, or
     * no news for that app.
     *
     * Stats are ambiguous — the game exposes none, or the profile hides them —
     * and so are a version check and a news lookup — no such app, or one that
     * publishes no server version or no news — so all of them answer 404 and
     * none says which. Steam refuses the news with a 403, but the client was
     * not forbidden anything.
     */
    public function notFound(
        SteamUserNotFoundException|StatsUnavailableException|AppNotFoundException
        |AppVersionUnavailableException|AppNewsUnavailableException $e,
        Request $request,
    ): Response {
        return $this->handler->render($request, new NotFoundHttpException($e->getMessage(), $e));
    }

    public function forbidden(ProfileNotPublicException $e, Request $request): Response
    {
        return $this->handler->render($request, new AccessDeniedHttpException($e->getMessage(), $e));
    }

    /**
     * The server address is input, typically a route parameter, and Steam
     * rejected its format.
     */
    public function unprocessable(InvalidServerAddressException $e, Request $request): Response
    {
        return $this->handler->render($request, new UnprocessableEntityHttpException($e->getMessage(), $e));
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
     * Steam never answered, or answered with a 5xx until the retries ran out.
     *
     * The base SDK raises the second as the root type, so this callback sees
     * every subclass nothing else rendered and has to hand each of them back.
     */
    public function unavailable(SteamApiException $e, Request $request): ?Response
    {
        $unreachable = $e instanceof SteamConnectionException
            || ($e::class === SteamApiException::class && $e->response?->serverError() === true);

        if (! $unreachable) {
            return null;
        }

        return $this->handler->render($request, new ServiceUnavailableHttpException(
            null,
            self::SERVICE_UNAVAILABLE,
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
     * the developer keeps the exception page saying what to fix.
     */
    public function misconfigured(
        InvalidApiKeyException|TooManySteamIdsException|ApiKeyNotConfiguredException
        |InvalidSteamLanguageException|InvalidSteamHttpOptionException|InvalidSteamRateLimitStoreException
        |InvalidSteamLoggingOptionException|FakeOutsideTestsException $e,
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
