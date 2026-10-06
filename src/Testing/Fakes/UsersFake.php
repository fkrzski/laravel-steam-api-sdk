<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing\Fakes;

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\FriendFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBanFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserGroupFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

/**
 * The ISteamUser endpoints, faked the way `Steam::users()` reaches them.
 */
final readonly class UsersFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function summaries(PlayerSummaryFactory ...$players): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerSummaries(...$players), GetPlayerSummariesRequest::class);

        return $this->fake;
    }

    public function bans(PlayerBanFactory ...$bans): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerBans(...$bans), GetPlayerBansRequest::class);

        return $this->fake;
    }

    public function friends(FriendFactory ...$friends): SteamFake
    {
        $this->fake->addResponse(SteamResponse::friendList(...$friends), GetFriendListRequest::class);

        return $this->fake;
    }

    public function friendsNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::profileNotPublic(), GetFriendListRequest::class);

        return $this->fake;
    }

    public function groups(UserGroupFactory ...$groups): SteamFake
    {
        $this->fake->addResponse(SteamResponse::userGroupList(...$groups), GetUserGroupListRequest::class);

        return $this->fake;
    }

    public function groupsNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::profileNotPublic(), GetUserGroupListRequest::class);

        return $this->fake;
    }

    public function resolveVanityUrl(SteamId $steamId): SteamFake
    {
        $this->fake->addResponse(SteamResponse::vanityUrl($steamId), ResolveVanityUrlRequest::class);

        return $this->fake;
    }

    public function vanityUrlNotFound(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::vanityNotFound(), ResolveVanityUrlRequest::class);

        return $this->fake;
    }
}
