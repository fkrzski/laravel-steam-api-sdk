<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Fkrzski\LaravelSteamApiSdk\Console\AboutSection;
use Fkrzski\LaravelSteamApiSdk\Console\InstallCommand;
use Fkrzski\LaravelSteamApiSdk\Exceptions\SteamApiKeyMissingException;
use Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;

final class SteamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/steam-api.php', 'steam-api');

        $this->app->singleton(SteamConnector::class, fn (): SteamConnector => new SteamConnector(
            new SteamConfig(
                apiKey: $this->steamApiKey(),
                rateLimitStore: new LaravelCacheStore(Cache::store()),
            ),
        ));

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

            AboutCommand::add('Steam API', AboutSection::class);

            $this->commands([
                InstallCommand::class,
            ]);
        }

        $this->registerRouteBinding();
    }

    /**
     * The configured Steam Web API key.
     *
     * Resolved from config rather than read once at register time, so the key is
     * only required when the connector is actually built.
     *
     * @throws SteamApiKeyMissingException when the key is unset, blank, or not a string
     */
    private function steamApiKey(): string
    {
        $apiKey = config('steam-api.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new SteamApiKeyMissingException;
        }

        return trim($apiKey);
    }

    /**
     * Bind the configured route parameter to a {@see SteamId} value object.
     *
     * The binder is resolved from the container so its behaviour can be swapped
     * by rebinding {@see SteamIdRouteBinding}.
     */
    private function registerRouteBinding(): void
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
