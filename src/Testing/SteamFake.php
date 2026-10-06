<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing;

use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\AppsFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\PlayersFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\StatsFake;
use Fkrzski\LaravelSteamApiSdk\Testing\Fakes\UsersFake;
use Saloon\Http\Faking\MockClient;

/**
 * The Saloon mock `Steam::fake()` attaches, faked through the same resource chain
 * the code under test calls.
 *
 * Each endpoint registers its {@see SteamResponse} builder under the request class
 * it sends and hands this mock back, so a chain always ends on something the
 * assertions can read.
 */
final class SteamFake extends MockClient
{
    public function users(): UsersFake
    {
        return new UsersFake($this);
    }

    public function players(): PlayersFake
    {
        return new PlayersFake($this);
    }

    public function stats(): StatsFake
    {
        return new StatsFake($this);
    }

    public function apps(): AppsFake
    {
        return new AppsFake($this);
    }
}
