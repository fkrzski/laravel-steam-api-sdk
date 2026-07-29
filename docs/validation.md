---
title: Validation rule
description: Validate Steam IDs in requests with SteamIdRule — a 64-bit ID or a profile URL passes, strict mode takes raw IDs only, and the messages are publishable.
---

`SteamIdRule` validates that user-supplied input resolves to a
[`SteamId`](/laravel-steam-api-sdk/guide#the-steamid-value-object). It mirrors the
[route binding](/laravel-steam-api-sdk/route-binding): a 64-bit ID or a
`/profiles/<id>` URL passes, anything else fails. The rule is **format-only** —
it never calls the Steam Web API, so it never adds latency or spends your daily
request budget inside a validation cycle.

## Using the rule

Add it to any rules array:

```php
use Fkrzski\LaravelSteamApiSdk\Rules\SteamIdRule;

$request->validate([
    'steam_id' => ['required', SteamIdRule::make()],
]);
```

The same inside a form request:

```php
use Fkrzski\LaravelSteamApiSdk\Rules\SteamIdRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkSteamAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'steam_id' => ['required', SteamIdRule::make()],
        ];
    }
}
```

The rule only reports whether the value is valid — it does not transform it. Turn
the validated string into a value object yourself, or let the
[`AsSteamId`](/laravel-steam-api-sdk/eloquent-cast) cast do it when you persist:

```php
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

$steamId = SteamId::tryFromInput($request->string('steam_id')->value());
```

## Strict mode

Call `strict()` to accept a raw 64-bit ID only — useful for an API endpoint that
documents an exact format rather than a form field a person types into:

```php
'steam_id' => ['required', SteamIdRule::make()->strict()],
```

| Input | Default | `strict()` |
| --- | --- | --- |
| `76561198000000000` | passes | passes |
| `https://steamcommunity.com/profiles/76561198000000000` | passes | fails |
| `https://steamcommunity.com/id/gabelogannewell` | fails | fails |
| `not-a-steam-id` | fails | fails |

A vanity URL never passes. Resolving one requires a Steam Web API call, which
this rule deliberately does not make — use
[`Steam::resolveVanityUrl()`](/laravel-steam-api-sdk/api-reference#resolvevanityurl)
after validation if you accept vanity names.

## Accepted types

Strings are the common case, but the rule also accepts an `int` (a JSON body
sends `76561198000000000` as an integer) and any `Stringable`, including a
`SteamId` itself. Surrounding whitespace is trimmed before the check. Every other
type — `null`, arrays, floats, plain objects — fails.

## Error messages

The messages ship as a package translation and are published with:

```bash
php artisan vendor:publish --tag=steam-api-translations
```

That drops `lang/vendor/steam-api/en/validation.php` into your app, where you can
reword either key or add a locale directory beside `en`:

```php
return [
    'steam_id' => 'The :attribute field must be a valid Steam ID or profile URL.',
    'steam_id_strict' => 'The :attribute field must be a valid 64-bit Steam ID.',
];
```

For a one-off override, pass a message the usual Laravel way instead:

```php
$request->validate(
    ['steam_id' => ['required', SteamIdRule::make()]],
    ['steam_id' => 'Paste your Steam profile link.'],
);
```
