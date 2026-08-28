<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\SteamApiKeyMissingException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Rendering\SteamExceptionRenderer;
use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Route;
use Saloon\RateLimitPlugin\Limit;
use Symfony\Component\HttpFoundation\Response;

mutates(SteamExceptionRenderer::class);

/**
 * Re-register the renderers on a handler of our own.
 *
 * The provider reads the flag when it boots, so a test that changes it has to
 * boot again — and a fresh handler keeps the callbacks the original boot left
 * behind out of the way.
 */
function bootExceptionRenderers(): void
{
    app()->instance(ExceptionHandler::class, new Handler(app()));

    new SteamServiceProvider(app())->boot();
}

function renderer(): SteamExceptionRenderer
{
    return app(SteamExceptionRenderer::class);
}

/**
 * A limit whose window closes in an hour, so `Retry-After` has something to
 * count down from.
 */
function limitExpiringIn(int $seconds): Limit
{
    return Limit::allow(100)->everySeconds(3600)->setExpiryTimestamp(time() + $seconds);
}

beforeEach(function (): void {
    bootExceptionRenderers();
});

it('renders an unresolved vanity name as a 404', function (): void {
    Steam::fake([ResolveVanityUrlRequest::class => SteamResponse::vanityNotFound()]);

    Route::get('steam/vanity', fn (): string => Steam::resolveVanityUrl('nobody')->value);

    $this->getJson('steam/vanity')
        ->assertNotFound()
        ->assertJsonPath('message', 'No Steam user found for vanity name "nobody".');
});

it('renders unavailable stats as a 404', function (): void {
    Steam::fake([GetPlayerAchievementsRequest::class => SteamResponse::statsRefused()]);

    Route::get('steam/achievements', fn (): array => Steam::achievements(steamId(), 440)->achievements);

    $this->getJson('steam/achievements')->assertNotFound();
});

it('renders a private profile as a 403', function (): void {
    Steam::fake([GetUserGroupListRequest::class => SteamResponse::profileNotPublic()]);

    Route::get('steam/groups', fn (): array => Steam::groups(steamId()));

    $this->getJson('steam/groups')->assertForbidden();
});

it('renders a spent quota as a 429 carrying Retry-After', function (): void {
    Route::get('steam/quota', function (): never {
        throw SteamRateLimitException::fromLimit(limitExpiringIn(3600));
    });

    $response = $this->getJson('steam/quota')->assertStatus(429);

    // The window closes on the wall clock, so the countdown may lose the second
    // spent getting here.
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThanOrEqual(3599)
        ->toBeLessThanOrEqual(3600);
});

it('still sends a Retry-After for a window that already closed', function (): void {
    $exception = SteamRateLimitException::fromLimit(limitExpiringIn(-30));

    $response = renderer()->rateLimited($exception, request());

    expect($response->getStatusCode())->toBe(429)
        ->and($response->headers->get('Retry-After'))->toBe('1');
});

it('renders a rejected api key as a 500, not the status steam sent', function (): void {
    config()->set('app.debug', false);

    Steam::fake([GetPlayerSummariesRequest::class => SteamResponse::invalidApiKey()]);

    Route::get('steam/summaries', fn (): array => Steam::summaries([steamId()]));

    $this->getJson('steam/summaries')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Server Error']);
});

it('renders an oversized batch as a 500', function (): void {
    config()->set('app.debug', false);

    Route::get('steam/batch', fn (): array => Steam::summaries(array_fill(0, 101, steamId())));

    $this->getJson('steam/batch')->assertStatus(500);
});

it('keeps the missing key message out of the response body', function (): void {
    config()->set('app.debug', false);

    Route::get('steam/key', function (): never {
        throw new SteamApiKeyMissingException;
    });

    $this->getJson('steam/key')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Server Error']);
});

it('leaves a misconfiguration to the debug page while debugging', function (): void {
    config()->set('app.debug', true);

    expect(renderer()->misconfigured(new SteamApiKeyMissingException, request()))->toBeNull();
});

it('renders a misconfiguration whatever the flag says', function (): void {
    config()->set(['app.debug' => false, 'steam-api.exceptions.render' => false]);

    bootExceptionRenderers();

    $response = renderer()->misconfigured(new SteamApiKeyMissingException, request());

    expect($response?->getStatusCode())->toBe(500);
});

it('leaves the failure alone once rendering is turned off', function (): void {
    config()->set(['app.debug' => false, 'steam-api.exceptions.render' => false]);

    bootExceptionRenderers();

    Steam::fake([ResolveVanityUrlRequest::class => SteamResponse::vanityNotFound()]);

    Route::get('steam/vanity', fn (): string => Steam::resolveVanityUrl('nobody')->value);

    $this->getJson('steam/vanity')->assertStatus(500);
});

// mergeConfigFrom() is shallow, so a config published before the key existed
// arrives without it — and this group renders by default.
it('renders when the published config drops the render key', function (): void {
    config()->set('steam-api.exceptions', []);

    bootExceptionRenderers();

    Steam::fake([ResolveVanityUrlRequest::class => SteamResponse::vanityNotFound()]);

    Route::get('steam/vanity', fn (): string => Steam::resolveVanityUrl('nobody')->value);

    $this->getJson('steam/vanity')->assertNotFound();
});

it('leaves a handler without renderable() untouched', function (): void {
    app()->instance(ExceptionHandler::class, new class implements ExceptionHandler
    {
        public function report(Throwable $e): void {}

        public function shouldReport(Throwable $e): bool
        {
            return false;
        }

        public function render($request, Throwable $e): Response
        {
            return new Response;
        }

        public function renderForConsole($output, Throwable $e): void {}
    });

    expect(function (): void {
        new SteamServiceProvider(app())->boot();
    })->not->toThrow(Throwable::class);
});
