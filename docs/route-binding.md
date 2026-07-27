---
title: Route binding
description: Resolve a route parameter straight into a SteamId value object — a 64-bit ID or a profile URL becomes a SteamId, an unresolvable value returns a 404.
---

The bridge registers a [route binding](https://laravel.com/docs/routing#route-model-binding)
for the `steamId` parameter. Any route segment matching that name is resolved into
a [`SteamId`](/laravel-steam-api-sdk/guide#the-steamid-value-object) value object
before it reaches your controller — no manual parsing, no validation boilerplate.

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

## Configuration

The binding is enabled by default and only ever activates for routes that use the
configured parameter name, so it is safe out of the box. Publish the config to
change it:

```php
'route_binding' => [
    'enabled' => true,
    'parameter' => 'steamId',
],
```

Set `enabled` to `false` to leave routing untouched, or change `parameter` to
claim a different segment name.

## Swapping the resolver

The parameter is resolved through
`Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding`, pulled from the
container on every request. Rebind it to change how a segment becomes a `SteamId`
— for example, to resolve a vanity name instead of returning a 404:

```php
use Fkrzski\LaravelSteamApiSdk\Routing\SteamIdRouteBinding;

$this->app->bind(SteamIdRouteBinding::class, MyVanityAwareResolver::class);
```

Any invokable with a `__invoke(string $value): SteamId` signature works; throw a
`Symfony\Component\HttpKernel\Exception\NotFoundHttpException` to keep the 404
semantics on unresolvable input.
