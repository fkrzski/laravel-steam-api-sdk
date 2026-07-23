---
title: Eloquent cast
description: Store a Steam ID on an Eloquent model with the AsSteamId cast — read it back as a SteamId value object, with validation on every read and write.
---

`AsSteamId` is an Eloquent [custom cast](https://laravel.com/docs/eloquent-mutators#custom-casts)
that keeps a Steam ID on a model as a plain column but exposes it as a
[`SteamId`](/laravel-steam-api-sdk/guide#the-steamid-value-object) value object. The value is
validated through `SteamId::fromSteamId64` on both read and write, so an invalid ID
can never round-trip silently.

## Applying the cast

Add the cast to a model's `casts()` method:

```php
use Fkrzski\LaravelSteamApiSdk\Casts\AsSteamId;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected function casts(): array
    {
        return [
            'steam_id' => AsSteamId::class,
        ];
    }
}
```

## The column

Store the column as a **`string`** — a 64-bit Steam ID overflows a signed
`bigint`:

```php
use Illuminate\Database\Schema\Blueprint;

Schema::table('users', function (Blueprint $table): void {
    $table->string('steam_id')->nullable();
});
```

## Reading and writing

Assign either a `SteamId` value object or a plain string; both are validated and
persisted as the canonical 64-bit string. Reading the attribute always gives you a
`SteamId` back:

```php
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

$user->steam_id = SteamId::fromSteamId64('76561198000000000');
$user->steam_id = '76561198000000000'; // a plain string works too
$user->save();

$user->steam_id;        // SteamId value object
$user->steam_id->value; // '76561198000000000'
```

## Validation and null

On both read and write the value passes through `SteamId::fromSteamId64`; an
invalid stored or assigned value throws `InvalidSteamIdException`. A non-scalar
stored value throws the same exception rather than casting silently. `null` is
preserved in both directions, so a nullable column stays nullable.
