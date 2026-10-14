<?php

declare(strict_types=1);

namespace Tests;

use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Laravel\Nightwatch\NightwatchServiceProvider;

/**
 * Registers Nightwatch ahead of the bridge, so the bridge finds it on boot as
 * it would in an application.
 */
abstract class NightwatchTestCase extends TestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NightwatchServiceProvider::class,
            SteamServiceProvider::class,
        ];
    }
}
