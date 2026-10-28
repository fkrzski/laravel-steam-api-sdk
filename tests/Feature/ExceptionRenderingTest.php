<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamHttpOptionException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLanguageException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLoggingOptionException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamRateLimitOptionException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Rendering\SteamExceptionRenderer;
use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Route;
use Saloon\Http\Faking\MockResponse;
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

beforeEach(function (): void {
    bootExceptionRenderers();
});

it('renders an unresolved vanity name as a 404', function (): void {
    Steam::fake()->users()->vanityUrlNotFound();

    Route::get('steam/vanity', fn (): string => Steam::users()->resolveVanityUrl('nobody')->value);

    $this->getJson('steam/vanity')
        ->assertNotFound()
        ->assertJsonPath('message', 'ResolveVanityURL: No Steam user found for vanity name "nobody".');
});

it('renders unavailable stats as a 404', function (): void {
    Steam::fake()->stats()->achievementsUnavailable();

    Route::get('steam/achievements', fn (): array => Steam::stats()->achievements(steamId(), 440)->achievements);

    $this->getJson('steam/achievements')->assertNotFound();
});

it('renders an app id steam does not know as a 404', function (): void {
    Steam::fake()->stats()->currentPlayersAppNotFound();

    Route::get('steam/players', fn (): int => Steam::stats()->currentPlayers(1));

    $this->getJson('steam/players')
        ->assertNotFound()
        ->assertJsonPath('message', 'GetNumberOfCurrentPlayers: No Steam app found for app ID 1.');
});

it('renders an app steam cannot check for updates as a 404', function (): void {
    Steam::fake()->apps()->upToDateCheckUnavailable();

    Route::get('steam/version', fn (): bool => Steam::apps()->upToDateCheck(999999999, 1)->isUpToDate);

    $this->getJson('steam/version')
        ->assertNotFound()
        ->assertJsonPath(
            'message',
            'UpToDateCheck: Steam cannot check app 999999999 for updates: no app has that ID, or it publishes no server version.',
        );
});

it('renders an app with no news as a 404', function (): void {
    Steam::fake()->news()->appNewsUnavailable();

    Route::get('steam/news', fn (): int => Steam::news()->appNews(480)->total);

    $this->getJson('steam/news')
        ->assertNotFound()
        ->assertJsonPath(
            'message',
            'GetNewsForApp: Steam returned no news for app 480: no app has that ID, or Steam does not publish its news.',
        );
});

it('renders a private profile as a 403', function (): void {
    Steam::fake()->users()->groupsNotPublic();

    Route::get('steam/groups', fn (): array => Steam::users()->groups(steamId()));

    $this->getJson('steam/groups')->assertForbidden();
});

it('renders a rejected server address as a 422', function (): void {
    Steam::fake()->apps()->serversAtAddressInvalid();

    Route::get('steam/servers', fn (): array => Steam::apps()->serversAtAddress('not-an-ip'));

    $this->getJson('steam/servers')
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'GetServersAtAddress: Steam rejected "not-an-ip" as a server address: it takes an IPv4 address, optionally with a query port.',
        );
});

it('renders a spent quota as a 429 carrying Retry-After', function (): void {
    Steam::fake([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 429, ['Retry-After' => '3600']),
    ]);

    Route::get('steam/quota', fn (): int => Steam::stats()->currentPlayers(440));

    $response = $this->getJson('steam/quota')->assertStatus(429);

    // The window closes on the wall clock, so the countdown may lose the second
    // spent getting here.
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThanOrEqual(3599)
        ->toBeLessThanOrEqual(3600);
});

it('still sends a Retry-After for a window that already closed', function (): void {
    $response = renderer()->rateLimited(closedRateLimitWindow(), request());

    expect($response->getStatusCode())->toBe(429)
        ->and($response->headers->get('Retry-After'))->toBe('1');
});

it('renders an unreachable steam as a 503 that keeps the reason out', function (): void {
    config()->set('app.debug', false);

    Steam::fake()->connectionFailed();

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'Service Unavailable']);
});

it('renders a 5xx that outlasted the retries as a 503', function (): void {
    config()->set(['app.debug' => false, 'steam-api.http.retry.tries' => 2]);

    Steam::fake([GetPlayerSummariesRequest::class => MockResponse::make([], 502)]);

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'Service Unavailable']);

    Steam::assertSentCount(2);
});

it('renders an answer that is not json as a 503 that keeps the reason out', function (): void {
    config()->set('app.debug', false);

    Steam::fake([GetPlayerSummariesRequest::class => MockResponse::make('<html><body>Service Unavailable</body></html>')]);

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'Service Unavailable']);
});

it('renders an answer missing its envelope as a 503', function (): void {
    Steam::fake([GetPlayerSummariesRequest::class => MockResponse::make(['response' => []])]);

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')->assertServiceUnavailable();
});

it('still renders an unreachable steam as a 503 while debugging', function (): void {
    config()->set('app.debug', true);

    Steam::fake()->connectionFailed();

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')->assertServiceUnavailable();
});

it('leaves a rejected key to the debug page rather than a 503', function (): void {
    config()->set('app.debug', true);

    Steam::fake()->invalidApiKey();

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')->assertStatus(500);
});

it('leaves a root failure steam answered without a 5xx as a 500', function (): void {
    config()->set('app.debug', false);

    Steam::fake()->apps()->serversAtAddressRefused();

    Route::get('steam/servers', fn (): array => Steam::apps()->serversAtAddress('127.0.0.1'));

    $this->getJson('steam/servers')->assertStatus(500);
});

it('hands back a root failure that carries no response', function (): void {
    expect(renderer()->unavailable(new SteamApiException('Something failed.'), request()))->toBeNull();
});

it('hands back a subclass that carries a 5xx', function (): void {
    Steam::fake()->apps()->sdrConfigAppNotFound();

    try {
        Steam::apps()->sdrConfig(999999999);
    } catch (AppNotFoundException $appNotFoundException) {
        expect($appNotFoundException->response?->status())->toBe(500)
            ->and(renderer()->unavailable($appNotFoundException, request()))->toBeNull();

        return;
    }

    throw new RuntimeException('Expected the lookup to fail.');
});

it('renders a rejected api key as a 500, not the status steam sent', function (): void {
    config()->set('app.debug', false);

    Steam::fake()->invalidApiKey();

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Server Error']);
});

it('renders a key the supported api list rejects as a 500', function (): void {
    config()->set('app.debug', false);

    Steam::fake()->webApi()->supportedApiListInvalidKey();

    Route::get('steam/apis', fn (): array => Steam::webApi()->supportedApis());

    $this->getJson('steam/apis')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Server Error']);
});

it('renders an oversized batch built by hand as a 500', function (): void {
    config()->set('app.debug', false);

    Steam::fake()->users()->summaries(PlayerSummaryFactory::new());

    Route::get('steam/batch', fn (): int => Steam::send(
        new GetPlayerSummariesRequest(array_fill(0, 101, steamId())),
    )->status());

    $this->getJson('steam/batch')->assertStatus(500);

    Steam::assertNothingSent();
});

it('keeps the missing key message out of the response body', function (): void {
    config()->set(['app.debug' => false, 'steam-api.key' => null]);

    Steam::fake()->users()->summaries();

    Route::get('steam/key', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/key')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Server Error']);
});

it('renders a rejected language as a 500', function (): void {
    config()->set(['app.debug' => false, 'steam-api.language' => 'klingon']);

    Route::get('steam/language', fn (): string => Steam::connector()->resolveBaseUrl());

    $this->getJson('steam/language')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Server Error']);
});

// Called directly: through a route, an exception nothing renders is the same bare 500.
it('renders a rejected http option as a 500', function (): void {
    config()->set('app.debug', false);

    $response = renderer()->misconfigured(new InvalidSteamHttpOptionException('retry.tries', '0'), request());

    expect($response?->getStatusCode())->toBe(500);
});

it('renders a rejected rate limit option as a 500', function (): void {
    config()->set('app.debug', false);

    $response = renderer()->misconfigured(InvalidSteamRateLimitOptionException::store('redis-x'), request());

    expect($response?->getStatusCode())->toBe(500);
});

it('renders a rejected logging option as a 500', function (): void {
    config()->set('app.debug', false);

    $response = renderer()->misconfigured(new InvalidSteamLoggingOptionException('channel', 'papertrail-x'), request());

    expect($response?->getStatusCode())->toBe(500);
});

it('leaves a misconfiguration to the debug page while debugging', function (): void {
    config()->set('app.debug', true);

    expect(renderer()->misconfigured(new InvalidSteamLanguageException('klingon'), request()))->toBeNull();
});

it('renders a misconfiguration whatever the flag says', function (): void {
    config()->set(['app.debug' => false, 'steam-api.exceptions.render' => false]);

    bootExceptionRenderers();

    $response = renderer()->misconfigured(new InvalidSteamLanguageException('klingon'), request());

    expect($response?->getStatusCode())->toBe(500);
});

it('leaves the failure alone once rendering is turned off', function (): void {
    config()->set(['app.debug' => false, 'steam-api.exceptions.render' => false]);

    bootExceptionRenderers();

    Steam::fake()->users()->vanityUrlNotFound();

    Route::get('steam/vanity', fn (): string => Steam::users()->resolveVanityUrl('nobody')->value);

    $this->getJson('steam/vanity')->assertStatus(500);
});

it('leaves an unreachable steam alone once rendering is turned off', function (): void {
    config()->set(['app.debug' => false, 'steam-api.exceptions.render' => false]);

    bootExceptionRenderers();

    Steam::fake()->connectionFailed();

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')->assertStatus(500);
});

it('leaves an unreadable answer alone once rendering is turned off', function (): void {
    config()->set(['app.debug' => false, 'steam-api.exceptions.render' => false]);

    bootExceptionRenderers();

    Steam::fake([GetPlayerSummariesRequest::class => MockResponse::make(['response' => []])]);

    Route::get('steam/summaries', fn (): array => Steam::users()->summaries([steamId()]));

    $this->getJson('steam/summaries')->assertStatus(500);
});

// mergeConfigFrom() is shallow, so a config published before the key existed
// arrives without it — and this group renders by default.
it('renders when the published config drops the render key', function (): void {
    config()->set('steam-api.exceptions', []);

    bootExceptionRenderers();

    Steam::fake()->users()->vanityUrlNotFound();

    Route::get('steam/vanity', fn (): string => Steam::users()->resolveVanityUrl('nobody')->value);

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
