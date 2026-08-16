<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding;
use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

mutates(SteamIdRouteBinding::class);

const ROUTE_STEAM_ID_64 = '76561198000000000';

function binding(): SteamIdRouteBinding
{
    return new SteamIdRouteBinding;
}

/**
 * Opt in and register the binding on the application's own router — the one the
 * HTTP kernel substitutes bindings on.
 */
function enableRouteBinding(): void
{
    config()->set('steam-api.route_binding.enabled', true);

    new SteamServiceProvider(app())->boot();
}

it('resolves a 64-bit steam id to a value object', function (): void {
    $steamId = binding()(ROUTE_STEAM_ID_64);

    expect($steamId->value)->toBe(ROUTE_STEAM_ID_64);
});

it('resolves a profile url to a value object', function (): void {
    $steamId = binding()('https://steamcommunity.com/profiles/'.ROUTE_STEAM_ID_64);

    expect($steamId->value)->toBe(ROUTE_STEAM_ID_64);
});

it('throws a 404 for an unresolvable value', function (): void {
    binding()('not-a-steam-id');
})->throws(NotFoundHttpException::class);

it('resolves a route parameter to a SteamId through the container', function (): void {
    enableRouteBinding();

    Route::middleware(SubstituteBindings::class)
        ->get('players/{steamId}', fn (SteamId $steamId): array => ['value' => $steamId->value]);

    $this->get('players/'.ROUTE_STEAM_ID_64)
        ->assertOk()
        ->assertExactJson(['value' => ROUTE_STEAM_ID_64]);
});

it('returns a 404 when the route parameter is not a steam id', function (): void {
    enableRouteBinding();

    Route::middleware(SubstituteBindings::class)
        ->get('players/{steamId}', fn (SteamId $steamId): string => $steamId->value);

    $this->get('players/not-a-steam-id')->assertNotFound();
});

it('leaves the parameter alone until the application opts in', function (): void {
    expect(app('router')->getBindingCallback('steamId'))->toBeNull();
});

it('registers the binding under the configured parameter once enabled', function (): void {
    config()->set('steam-api.route_binding.enabled', true);

    $router = new Router(new Dispatcher, app());
    app()->instance('router', $router);

    new SteamServiceProvider(app())->boot();

    expect($router->getBindingCallback('steamId'))->not->toBeNull();
});

it('does not register the binding when disabled', function (): void {
    config()->set('steam-api.route_binding.enabled', false);

    $router = new Router(new Dispatcher, app());
    app()->instance('router', $router);

    new SteamServiceProvider(app())->boot();

    expect($router->getBindingCallback('steamId'))->toBeNull();
});

// mergeConfigFrom() is shallow, so a config published before the key existed
// arrives without it.
it('does not register the binding when the published config drops the enabled key', function (): void {
    config()->set('steam-api.route_binding', ['parameter' => 'steamId']);

    $router = new Router(new Dispatcher, app());
    app()->instance('router', $router);

    new SteamServiceProvider(app())->boot();

    expect($router->getBindingCallback('steamId'))->toBeNull();
});

it('registers the binding under a custom parameter name', function (): void {
    config()->set([
        'steam-api.route_binding.enabled' => true,
        'steam-api.route_binding.parameter' => 'gamer',
    ]);

    $router = new Router(new Dispatcher, app());
    app()->instance('router', $router);

    new SteamServiceProvider(app())->boot();

    expect($router->getBindingCallback('gamer'))->not->toBeNull()
        ->and($router->getBindingCallback('steamId'))->toBeNull();
});
