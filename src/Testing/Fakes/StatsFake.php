<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing\Fakes;

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GameSchemaFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\GlobalAchievementFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerAchievementsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\UserStatsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;

/**
 * The ISteamUserStats endpoints, faked the way `Steam::stats()` reaches them.
 */
final readonly class StatsFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function userStats(UserStatsFactory $stats): SteamFake
    {
        $this->fake->addResponse(SteamResponse::userStats($stats), GetUserStatsForGameRequest::class);

        return $this->fake;
    }

    public function achievements(PlayerAchievementsFactory $achievements): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerAchievements($achievements), GetPlayerAchievementsRequest::class);

        return $this->fake;
    }

    public function currentPlayers(int $count): SteamFake
    {
        $this->fake->addResponse(SteamResponse::currentPlayers($count), GetNumberOfCurrentPlayersRequest::class);

        return $this->fake;
    }

    public function globalAchievements(GlobalAchievementFactory ...$achievements): SteamFake
    {
        $this->fake->addResponse(
            SteamResponse::globalAchievements(...$achievements),
            GetGlobalAchievementPercentagesForAppRequest::class,
        );

        return $this->fake;
    }

    public function schema(GameSchemaFactory $schema): SteamFake
    {
        $this->fake->addResponse(SteamResponse::gameSchema($schema), GetSchemaForGameRequest::class);

        return $this->fake;
    }
}
