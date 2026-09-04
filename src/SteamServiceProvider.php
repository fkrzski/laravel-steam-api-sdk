<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Fkrzski\LaravelSteamApiSdk\Console\AboutSection;
use Fkrzski\LaravelSteamApiSdk\Console\InstallCommand;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamIdBinder;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamManager as SteamManagerContract;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLanguageException;
use Fkrzski\LaravelSteamApiSdk\Http\RequiresConfiguredApiKey;
use Fkrzski\LaravelSteamApiSdk\Rendering\SteamExceptionRenderer;
use Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;

final class SteamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/steam-api.php', 'steam-api');

        $this->app->scoped(SteamConnector::class, function (): SteamConnector {
            $apiKey = $this->steamApiKey();

            $connector = new SteamConnector(
                new SteamConfig(
                    apiKey: $apiKey,
                    rateLimitStore: new LaravelCacheStore(Cache::store()),
                    language: $this->steamLanguage(),
                ),
            );

            // Unnamed on purpose: a named pipe is unique, and a second one under
            // the same name throws rather than replacing the first.
            $connector->middleware()->onRequest(new RequiresConfiguredApiKey($apiKey !== ''));

            return $connector;
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
        });
    }

    /**
     * The configured Steam Web API key, blank when none is set.
     *
     * A key that is unset, blank or not a string is not an error here: the
     * connector still has to be built for the endpoints Steam serves
     * anonymously. {@see RequiresConfiguredApiKey} refuses the requests that do
     * need one.
     */
    private function steamApiKey(): string
    {
        $apiKey = config('steam-api.key');

        return is_string($apiKey) ? trim($apiKey) : '';
    }

    /**
     * The configured default language, sent on every request that localises its
     * payload.
     *
     * Resolved from config alongside the key, so a rejected code surfaces on the
     * first Steam call rather than at boot. The shipped config defaults to
     * English; blank it out and no language goes out at all, leaving the choice
     * to Steam.
     *
     * @throws InvalidSteamLanguageException when the value is not one of Steam's codes
     */
    private function steamLanguage(): ?Language
    {
        $language = config('steam-api.language');
        $code = is_string($language) ? trim($language) : '';

        if ($code === '') {
            return null;
        }

        return Language::tryFrom($code) ?? throw new InvalidSteamLanguageException($code);
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

        /** @var string $parameter */
        $parameter = config('steam-api.route_binding.parameter', 'steamId');

        /** @var Router $router */
        $router = $this->app->make('router');

        $router->bind($parameter, function (string $value): SteamId {
            $binder = $this->app->make(SteamIdBinder::class);

            return $binder($value);
        });
    }
}
