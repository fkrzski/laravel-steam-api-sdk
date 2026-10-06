<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing\Fakes;

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppVersionCheckFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameServerFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\SdrConfigFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;

/**
 * The ISteamApps endpoints, faked the way `Steam::apps()` reaches them.
 */
final readonly class AppsFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function upToDateCheck(AppVersionCheckFactory $check): SteamFake
    {
        $this->fake->addResponse(SteamResponse::upToDateCheck($check), UpToDateCheckRequest::class);

        return $this->fake;
    }

    public function upToDateCheckUnavailable(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::appVersionUnavailable(), UpToDateCheckRequest::class);

        return $this->fake;
    }

    public function serversAtAddress(GameServerFactory ...$servers): SteamFake
    {
        $this->fake->addResponse(SteamResponse::serversAtAddress(...$servers), GetServersAtAddressRequest::class);

        return $this->fake;
    }

    public function serversAtAddressInvalid(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::invalidServerAddress(), GetServersAtAddressRequest::class);

        return $this->fake;
    }

    /**
     * Raised as the root `SteamApiException`, so only the message names this failure.
     */
    public function serversAtAddressRefused(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::serversAtAddressRefused(), GetServersAtAddressRequest::class);

        return $this->fake;
    }

    public function sdrConfig(SdrConfigFactory $config): SteamFake
    {
        $this->fake->addResponse(SteamResponse::sdrConfig($config), GetSdrConfigRequest::class);

        return $this->fake;
    }

    public function sdrConfigAppNotFound(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::sdrConfigAppNotFound(), GetSdrConfigRequest::class);

        return $this->fake;
    }
}
