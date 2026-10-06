<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing\Fakes;

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\CommunityBadgeQuestFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\OwnedGameFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerBadgesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\RecentlyPlayedGamesFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;

/**
 * The IPlayerService endpoints, faked the way `Steam::players()` reaches them.
 */
final readonly class PlayersFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function ownedGames(OwnedGameFactory ...$games): SteamFake
    {
        $this->fake->addResponse(SteamResponse::ownedGames(...$games), GetOwnedGamesRequest::class);

        return $this->fake;
    }

    public function recentlyPlayedGames(RecentlyPlayedGamesFactory $games): SteamFake
    {
        $this->fake->addResponse(SteamResponse::recentlyPlayedGames($games), GetRecentlyPlayedGamesRequest::class);

        return $this->fake;
    }

    public function steamLevel(int $level): SteamFake
    {
        $this->fake->addResponse(SteamResponse::steamLevel($level), GetSteamLevelRequest::class);

        return $this->fake;
    }

    public function badges(PlayerBadgesFactory $badges): SteamFake
    {
        $this->fake->addResponse(SteamResponse::badges($badges), GetBadgesRequest::class);

        return $this->fake;
    }

    public function communityBadgeProgress(CommunityBadgeQuestFactory ...$quests): SteamFake
    {
        $this->fake->addResponse(
            SteamResponse::communityBadgeProgress(...$quests),
            GetCommunityBadgeProgressRequest::class,
        );

        return $this->fake;
    }
}
