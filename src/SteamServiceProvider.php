<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/steam-api.php' => $this->app->configPath('steam-api.php'),
            ], 'steam-api-config');
        }
    }
}
