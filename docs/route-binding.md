---
title: Route binding
description: Opt in to resolving a route parameter into a SteamId value object — a 64-bit ID or a profile URL becomes a SteamId, an unresolvable value returns a 404.
---

The bridge ships a [route binding](https://laravel.com/docs/routing#route-model-binding)
that resolves a route parameter into a
[`SteamId`](/laravel-steam-api-sdk/guide#the-steamid-value-object) value object
before it reaches your controller — no manual parsing, no validation boilerplate.

It is **off by default**. `Router::bind()` claims a parameter name across your
whole application, and this package ships no routes of its own, so the only routes
the binding can ever claim are yours.

## Enabling the binding

Publish the config and set `enabled`:

```php
// config/steam-api.php

'route_binding' => [
    'enabled' => true,
    'parameter' => 'steamId',
],
```

`parameter` is the segment name to claim. It applies to **every** route using that
name, so pick one your application does not already use for something else.

## Using the binding

Name a route parameter `steamId` and type-hint the value object:

```php
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

Route::get('/players/{steamId}', function (SteamId $steamId) {
    return Steam::playerSummaries([$steamId]);
});
```

A request to `/players/76561198000000000` hands your closure a ready `SteamId`.
The value is resolved through `SteamId::tryFromInput`, so a `/profiles/<id>` URL
segment resolves too, while an unresolvable value aborts the request with a
**404** — the same behaviour Laravel gives a missing model.

## Format only, so vanity names 404

The binder never calls the Steam Web API — it parses the segment and nothing more:

```text
/players/76561198000000000  → SteamId
/players/gaben              → 404
```

That 404 is thrown during `SubstituteBindings`, **before** your controller runs.
If your application resolves vanity names itself, keep your own binder for that
parameter, claim a different `parameter` name here, or swap the resolver below.

## Swapping the resolver

The parameter is resolved through
`Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding`, pulled from the container
on every request. A replacement must be invokable, take the segment as a `string`,
return a `SteamId`, and throw a
`Symfony\Component\HttpKernel\Exception\NotFoundHttpException` on input it cannot
resolve.

Wrap the shipped binder rather than reimplementing it — it already handles every
format it knows, so you only pay for an API call on what it rejects:

```php
namespace App\Routing;

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding;
use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class VanityAwareSteamIdBinding
{
    public function __construct(private SteamIdRouteBinding $binding) {}

    public function __invoke(string $value): SteamId
    {
        try {
            return ($this->binding)($value);
        } catch (NotFoundHttpException) {
            // Neither an ID nor a profile URL — try it as a vanity name.
        }

        try {
            return Steam::resolveVanityUrl($value);
        } catch (SteamUserNotFoundException) {
            throw new NotFoundHttpException(sprintf('"%s" is not a Steam profile.', $value));
        }
    }
}
```

Register it in a service provider:

```php
use App\Routing\VanityAwareSteamIdBinding;
use Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding;

$this->app->bind(
    SteamIdRouteBinding::class,
    fn (): VanityAwareSteamIdBinding => new VanityAwareSteamIdBinding(new SteamIdRouteBinding),
);
```

Build the wrapped binder with `new`: autowiring that constructor would resolve
`SteamIdRouteBinding` back to your replacement. `SteamIdRouteBinding` is `final`,
so the seam is the container binding, never a subclass.

Vanity routes now cost a Steam call per request — cache the resolution if the route
is hot.

## Checking what is claimed

```bash
php artisan about --only=steam_api
```

```text
  Route Binding ..................................... enabled (steamId)
```
