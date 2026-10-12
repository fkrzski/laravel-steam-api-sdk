<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing\Fakes;

use DateTimeInterface;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\WebApiInterfaceFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;

/**
 * The ISteamWebAPIUtil endpoints, faked the way `Steam::webApi()` reaches them.
 */
final readonly class WebApiFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function serverInfo(DateTimeInterface $time): SteamFake
    {
        $this->fake->addResponse(SteamResponse::serverInfo($time), GetServerInfoRequest::class);

        return $this->fake;
    }

    public function supportedApiList(WebApiInterfaceFactory ...$interfaces): SteamFake
    {
        $this->fake->addResponse(SteamResponse::supportedApiList(...$interfaces), GetSupportedApiListRequest::class);

        return $this->fake;
    }

    public function supportedApiListInvalidKey(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::supportedApiListInvalidKey(), GetSupportedApiListRequest::class);

        return $this->fake;
    }
}
