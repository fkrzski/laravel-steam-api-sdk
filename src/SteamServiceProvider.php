<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Fkrzski\LaravelSteamApiSdk\Console\AboutSection;
use Fkrzski\LaravelSteamApiSdk\Console\InstallCommand;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamIdBinder;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamLanguageResolver;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamManager as SteamManagerContract;
use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestFailed;
use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestSending;
use Fkrzski\LaravelSteamApiSdk\Events\SteamResponseReceived;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamConfigException;
use Fkrzski\LaravelSteamApiSdk\Localization\LocaleLanguageResolver;
use Fkrzski\LaravelSteamApiSdk\Logging\SteamTrafficLogger;
use Fkrzski\LaravelSteamApiSdk\Monitoring\NightwatchReporting;
use Fkrzski\LaravelSteamApiSdk\Rendering\SteamExceptionRenderer;
use Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Hooks\RequestSending;
use Fkrzski\SteamApiSdk\Hooks\ResponseReceived;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Facades\Nightwatch;
use Saloon\Contracts\Sender;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;
use Throwable;
use UnexpectedValueException;

final class SteamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/steam-api.php', 'steam-api');

        $this->app->scoped(SteamConnector::class, function (Application $app): SteamConnector {
            $settings = $app->make(SteamSettings::class);
            $settings->validateLogging();

            return $this->dispatchEvents(new SteamConnector(new SteamConfig(
                apiKey: $this->steamApiKey(),
                rateLimitStore: new LaravelCacheStore(Cache::store($settings->rateLimitStore())),
                language: $this->steamLanguage(),
                connectTimeout: $settings->connectTimeout(),
                requestTimeout: $settings->requestTimeout(),
                tries: $settings->tries(),
                retryInterval: $settings->retryInterval(),
                exponentialBackoff: $settings->exponentialBackoff(),
                rateLimits: $settings->rateLimits(),
                sender: $this->steamSender($app, $settings->sender()),
                tooManyRequestsCooldown: $settings->rateLimitCooldown(),
            )), $app);
        });

        $this->app->scoped(
            SteamManager::class,
            fn (Application $app): SteamManager => new SteamManager(
                fn (): SteamConnector => $app->make(SteamConnector::class),
                $app,
            ),
        );

        // Resolves through the concrete key, so both names hand back one instance.
        $this->app->scoped(
            SteamManagerContract::class,
            fn (Application $app): SteamManagerContract => $app->make(SteamManager::class),
        );

        $this->app->bind(SteamIdBinder::class, SteamIdRouteBinding::class);
        $this->app->bind(SteamLanguageResolver::class, LocaleLanguageResolver::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'steam-api');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/steam-api.php' => $this->app->configPath('steam-api.php'),
            ], 'steam-api-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/steam-api'),
            ], 'steam-api-translations');

            AboutCommand::add('Steam API', AboutSection::class);

            $this->commands([
                InstallCommand::class,
            ]);
        }

        $this->registerRouteBinding();
        $this->registerExceptionRenderers();
        $this->registerLogging();
        $this->registerNightwatch();
    }

    /**
     * Turn the connector's hooks into events.
     *
     * A hook added on the scoped connector dies with the scope; a listener on the
     * dispatcher does not. The dispatcher is resolved per event, so one faked after
     * the connector was built still hears it.
     */
    private function dispatchEvents(SteamConnector $connector, Application $app): SteamConnector
    {
        return $connector
            ->onRequest(static function (RequestSending $sending) use ($app): void {
                $app->make(Dispatcher::class)->dispatch(new SteamRequestSending(
                    $sending->method,
                    $sending->query,
                    $sending->attempt,
                ));
            })
            ->onResponse(static function (ResponseReceived $received) use ($app): void {
                $app->make(Dispatcher::class)->dispatch(new SteamResponseReceived(
                    $received->method,
                    $received->query,
                    $received->attempt,
                    $received->status,
                    $received->duration,
                ));
            })
            ->onFailure(static function (Throwable $exception) use ($app): void {
                $app->make(Dispatcher::class)->dispatch(new SteamRequestFailed($exception));
            });
    }

    /**
     * Log Steam traffic through listeners on its events rather than hooks on the
     * connector, so `Event::fake()` silences the logging along with the events.
     */
    private function registerLogging(): void
    {
        $events = $this->app->make(Dispatcher::class);

        $events->listen(SteamRequestFailed::class, [SteamTrafficLogger::class, 'logFailure']);
        $events->listen(SteamResponseReceived::class, [SteamTrafficLogger::class, 'logResponse']);
    }

    /**
     * Keep the API key out of the URLs Nightwatch records, whichever sender
     * carried the request. Registered once here: Nightwatch's core outlives
     * every scope, so a callback added with each connector would pile up.
     */
    private function registerNightwatch(): void
    {
        $nightwatch = $this->app->make(NightwatchReporting::class);

        if ($nightwatch->installed()) {
            Nightwatch::redactOutgoingRequests($nightwatch->redactApiKey(...));
        }
    }

    /**
     * Register the render callbacks that map a Steam failure onto a status.
     *
     * The handler is reached after it resolves rather than at boot: `renderable()`
     * lives on the framework's concrete handler, not on the contract this package
     * depends on, and an application free to swap it is free to drop the method.
     * Registering late also puts these behind the application's own callbacks,
     * so a `renderable()` in `bootstrap/app.php` still wins.
     */
    private function registerExceptionRenderers(): void
    {
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! $handler instanceof Handler) {
                return;
            }

            $renderer = $this->app->make(SteamExceptionRenderer::class);

            $handler->renderable($renderer->misconfigured(...));

            if (config('steam-api.exceptions.render', true) === false) {
                return;
            }

            $handler->renderable($renderer->notFound(...));
            $handler->renderable($renderer->forbidden(...));
            $handler->renderable($renderer->rateLimited(...));
            $handler->renderable($renderer->unprocessable(...));
            $handler->renderable($renderer->unavailable(...));
        });
    }

    /**
     * The configured Steam Web API key, null when none is set.
     *
     * A key that is unset, blank or not a string is not an error here: the
     * connector still has to be built for the endpoints Steam serves
     * anonymously, and it refuses the requests that do need one itself.
     * Null rather than blank, because {@see SteamConfig} rejects a blank key.
     */
    private function steamApiKey(): ?string
    {
        $apiKey = config('steam-api.key');
        $apiKey = is_string($apiKey) ? trim($apiKey) : '';

        return $apiKey === '' ? null : $apiKey;
    }

    /**
     * The configured sender, built by the container each time the connector is,
     * so a binding of its class decides how. Left unset, it is a GuzzleSender
     * reporting to Nightwatch while Nightwatch is enabled, or null for the base SDK's.
     *
     * @throws UnexpectedValueException when a binding hands back something else
     */
    private function steamSender(Application $app, ?string $class): ?Sender
    {
        if ($class === null) {
            $nightwatch = $app->make(NightwatchReporting::class);

            return $nightwatch->enabled() ? $nightwatch->sender() : null;
        }

        $sender = $app->make($class);

        return $sender instanceof Sender ? $sender : throw new UnexpectedValueException(sprintf(
            'The container resolved the configured Steam sender "%s" to %s, which does not implement %s.',
            $class,
            get_debug_type($sender),
            Sender::class,
        ));
    }

    /**
     * The default language, sent on every request that localises its payload.
     *
     * A configured code wins, an unset value falls to the locale, and one blanked
     * out on purpose sends no language at all. Resolved from config alongside the
     * key, so a rejected code surfaces on the first Steam call rather than at boot.
     *
     * @throws InvalidSteamConfigException when the value is not one of Steam's codes
     */
    private function steamLanguage(): ?Language
    {
        $language = config('steam-api.language');

        if ($language === null) {
            return $this->localeLanguage();
        }

        $code = is_string($language) ? trim($language) : '';

        if ($code === '') {
            return null;
        }

        return Language::tryFrom($code) ?? throw InvalidSteamConfigException::language($code);
    }

    /**
     * The locale is read once, when the connector is first resolved — the
     * connector is scoped, so a later `setLocale()` does not reach it. Switching
     * mid-request means passing the language to the call.
     */
    private function localeLanguage(): ?Language
    {
        $resolver = $this->app->make(SteamLanguageResolver::class);

        return $resolver($this->app->getLocale());
    }

    /**
     * Bind the configured route parameter to a {@see SteamId} value object.
     *
     * The binder is resolved from the container so its behaviour can be swapped
     * by rebinding {@see SteamIdBinder}.
     */
    private function registerRouteBinding(): void
    {
        if (config('steam-api.route_binding.enabled', false) !== true) {
            return;
        }

        $parameter = config('steam-api.route_binding.parameter');
        $parameter = is_string($parameter) ? trim($parameter) : '';
        $parameter = $parameter === '' ? 'steamId' : $parameter;

        /** @var Router $router */
        $router = $this->app->make('router');

        $router->bind($parameter, function (string $value): SteamId {
            $binder = $this->app->make(SteamIdBinder::class);

            return $binder($value);
        });
    }
}
