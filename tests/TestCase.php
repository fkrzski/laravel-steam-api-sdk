<?php

declare(strict_types=1);

namespace Tests;

use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Illuminate\Testing\PendingCommand;
use LogicException;
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

    /**
     * `artisan()` widens to `PendingCommand|int` — it returns the exit code
     * instead once console output is no longer mocked. Every console test here
     * chains expectations, so resolve the union in one place.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function pendingCommand(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        if (! $pending instanceof PendingCommand) {
            throw new LogicException('Console output is not mocked, so '.$command.' cannot be asserted on.');
        }

        return $pending;
    }
}
