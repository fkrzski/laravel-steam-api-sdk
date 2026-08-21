<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeNotInstalledException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;

mutates(SteamManager::class);

it('records nothing until a request is sent', function (): void {
    fakeSteamEndpoints();

    expect(Steam::recorded())->toBeEmpty()
        ->and(Steam::lastRequest())->toBeNull()
        ->and(Steam::lastResponse())->toBeNull();
});

it('records every response in the order they came back', function (): void {
    fakeSteamEndpoints();

    Steam::resolveVanityUrl('gabelogannewell');
    Steam::playerSummaries([steamId()]);

    $recorded = Steam::recorded();

    expect($recorded)->toHaveCount(2)
        ->and($recorded[0]->getPendingRequest()->getRequest())->toBeInstanceOf(ResolveVanityUrlRequest::class)
        ->and($recorded[1]->getPendingRequest()->getRequest())->toBeInstanceOf(GetPlayerSummariesRequest::class);
});

it('exposes the last request and the response it came back with', function (): void {
    fakeSteamEndpoints();

    Steam::playerSummaries([steamId()]);
    Steam::resolveVanityUrl('gabelogannewell');

    expect(Steam::lastRequest())->toBeInstanceOf(ResolveVanityUrlRequest::class)
        ->and(Steam::lastResponse()?->status())->toBe(200);
});

it('refuses to read the traffic without a fake', function (): void {
    expect(function (): void {
        Steam::recorded();
    })->toThrow(FakeNotInstalledException::class, 'Call Steam::fake()')
        ->and(function (): void {
            Steam::lastRequest();
        })->toThrow(FakeNotInstalledException::class)
        ->and(function (): void {
            Steam::lastResponse();
        })->toThrow(FakeNotInstalledException::class);
});
