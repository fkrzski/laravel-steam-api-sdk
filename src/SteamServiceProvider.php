<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;

class SteamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/steam-api.php', 'steam-api');

        $this->app->singleton(SteamConnector::class, function (): SteamConnector {
            /** @var string $apiKey */
            $apiKey = config('steam-api.key', '');

            return new SteamConnector(
                new SteamConfig(
                    apiKey: $apiKey,
                    rateLimitStore: new LaravelCacheStore(Cache::store()),
                ),
            );
        });

        $this->app->singleton(
            SteamManager::class,
            fn (): SteamManager => new SteamManager(fn (): SteamConnector => $this->app->make(SteamConnector::class)),
        );
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
        }

        $this->registerRouteBinding();
    }

    /**
     * Bind the configured route parameter to a {@see SteamId} value object.
     *
     * The binder is resolved from the container so its behaviour can be swapped
     * by rebinding {@see SteamIdRouteBinding}.
     */
    protected function registerRouteBinding(): void
    {
        if (config('steam-api.route_binding.enabled', true) !== true) {
            return;
        }

        /** @var string $parameter */
        $parameter = config('steam-api.route_binding.parameter', 'steamId');

        /** @var Router $router */
        $router = $this->app->make('router');

        $router->bind($parameter, function (string $value): SteamId {
            /** @var SteamIdRouteBinding $binding */
            $binding = $this->app->make(SteamIdRouteBinding::class);

            return $binding($value);
        });
    }
}
