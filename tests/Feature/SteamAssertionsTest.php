<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeNotInstalledException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\SteamManager;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use PHPUnit\Framework\AssertionFailedError;

mutates(SteamManager::class);

it('asserts a request was sent', function (): void {
    fakeSteamEndpoints();

    Steam::summaries([steamId()]);

    Steam::assertSent(GetPlayerSummariesRequest::class);
});

it('asserts a request was sent by closure', function (): void {
    fakeSteamEndpoints();

    Steam::summaries([steamId()]);

    Steam::assertSent(
        fn (GetPlayerSummariesRequest $request): bool => $request->steamIds[0]->value === '76561198000000000',
    );
});

it('fails when the expected request was not sent', function (): void {
    fakeSteamEndpoints();

    expect(function (): void {
        Steam::assertSent(GetPlayerSummariesRequest::class);
    })
        ->toThrow(AssertionFailedError::class, 'An expected request was not sent.');
});

it('asserts a request was not sent', function (): void {
    fakeSteamEndpoints();

    Steam::resolveVanityUrl('gabelogannewell');

    Steam::assertNotSent(GetPlayerSummariesRequest::class);
});

it('fails when an unexpected request was sent', function (): void {
    fakeSteamEndpoints();

    Steam::resolveVanityUrl('gabelogannewell');

    expect(function (): void {
        Steam::assertNotSent(ResolveVanityUrlRequest::class);
    })
        ->toThrow(AssertionFailedError::class, 'An unexpected request was sent.');
});

it('asserts nothing was sent', function (): void {
    fakeSteamEndpoints();

    Steam::assertNothingSent();
});

it('fails when something was sent', function (): void {
    fakeSteamEndpoints();

    Steam::resolveVanityUrl('gabelogannewell');

    expect(function (): void {
        Steam::assertNothingSent();
    })->toThrow(AssertionFailedError::class, 'Requests were sent.');
});

it('asserts how many requests were sent', function (): void {
    fakeSteamEndpoints();

    Steam::summaries([steamId()]);
    Steam::resolveVanityUrl('gabelogannewell');

    Steam::assertSentCount(2);
});

it('narrows the count to a single request class', function (): void {
    fakeSteamEndpoints();

    Steam::summaries([steamId()]);
    Steam::resolveVanityUrl('gabelogannewell');

    Steam::assertSentCount(1, ResolveVanityUrlRequest::class);
});

it('fails when the count does not match', function (): void {
    fakeSteamEndpoints();

    Steam::resolveVanityUrl('gabelogannewell');

    expect(function (): void {
        Steam::assertSentCount(2);
    })->toThrow(AssertionFailedError::class)
        ->and(function (): void {
            Steam::assertSentCount(1, GetPlayerSummariesRequest::class);
        })->toThrow(AssertionFailedError::class);
});

it('asserts the order the requests were sent in', function (): void {
    fakeSteamEndpoints();

    Steam::resolveVanityUrl('gabelogannewell');
    Steam::summaries([steamId()]);

    Steam::assertSentInOrder([
        ResolveVanityUrlRequest::class,
        GetPlayerSummariesRequest::class,
    ]);
});

it('fails when a request in the order was never sent', function (): void {
    fakeSteamEndpoints();

    Steam::resolveVanityUrl('gabelogannewell');

    $assert = function (): void {
        Steam::assertSentInOrder([GetPlayerSummariesRequest::class]);
    };

    expect($assert)->toThrow(AssertionFailedError::class, 'An expected request (#1) was not sent.');
});

it('refuses to assert without a fake', function (): void {
    expect(function (): void {
        Steam::assertNothingSent();
    })->toThrow(FakeNotInstalledException::class, 'Call Steam::fake()')
        ->and(function (): void {
            Steam::assertSent(ResolveVanityUrlRequest::class);
        })->toThrow(FakeNotInstalledException::class);
});
