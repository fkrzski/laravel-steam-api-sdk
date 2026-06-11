<?php

declare(strict_types=1);

namespace Tests;

use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SteamServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('steam-api.key', 'test-steam-api-key');
    }
}
