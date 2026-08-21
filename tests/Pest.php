<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->tia()->locally();

function steamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function fakeSteamEndpoints(): void
{
    Steam::fake([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new(),
        ),
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(steamId()),
    ]);
}
