<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Casts\AsSteamId;
use Fkrzski\SteamApiSdk\Exceptions\InvalidSteamIdException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Database\Eloquent\Model;

mutates(AsSteamId::class);

function castModel(): Model
{
    return new class extends Model {};
}

function asSteamId(): AsSteamId
{
    return new AsSteamId;
}

function castingModel(): Model
{
    return new class extends Model
    {
        /**
         * @return array<string, string>
         */
        protected function casts(): array
        {
            return ['steam_id' => AsSteamId::class];
        }
    };
}

const STEAM_ID_64 = '76561198000000000';

it('casts a null db value to null on get', function (): void {
    expect(asSteamId()->get(castModel(), 'steam_id', null, []))->toBeNull();
});

it('casts a stored steam id 64 to a SteamId value object on get', function (): void {
    $result = asSteamId()->get(castModel(), 'steam_id', STEAM_ID_64, []);

    expect($result)->toBeInstanceOf(SteamId::class)
        ->and($result?->value)->toBe(STEAM_ID_64);
});

it('throws when the stored value is not a valid steam id 64 on get', function (): void {
    asSteamId()->get(castModel(), 'steam_id', 'not-a-steam-id', []);
})->throws(InvalidSteamIdException::class);

it('casts a stored integer steam id 64 to a SteamId value object on get', function (): void {
    $result = asSteamId()->get(castModel(), 'steam_id', 76561198000000000, []);

    expect($result)->toBeInstanceOf(SteamId::class)
        ->and($result?->value)->toBe(STEAM_ID_64);
});

it('throws when the stored value is not a scalar on get', function (): void {
    asSteamId()->get(castModel(), 'steam_id', ['not', 'scalar'], []);
})->throws(InvalidSteamIdException::class);

it('casts a null value to null on set', function (): void {
    expect(asSteamId()->set(castModel(), 'steam_id', null, []))->toBeNull();
});

it('serializes a SteamId value object to its id 64 string on set', function (): void {
    $value = SteamId::fromSteamId64(STEAM_ID_64);

    expect(asSteamId()->set(castModel(), 'steam_id', $value, []))->toBe(STEAM_ID_64);
});

it('serializes a steam id 64 string on set', function (): void {
    expect(asSteamId()->set(castModel(), 'steam_id', STEAM_ID_64, []))->toBe(STEAM_ID_64);
});

it('throws when the value is not a valid steam id 64 on set', function (): void {
    asSteamId()->set(castModel(), 'steam_id', 'not-a-steam-id', []);
})->throws(InvalidSteamIdException::class);

it('serializes an integer steam id 64 on set', function (): void {
    expect(asSteamId()->set(castModel(), 'steam_id', 76561198000000000, []))->toBe(STEAM_ID_64);
});

it('serializes a stringable steam id 64 on set', function (): void {
    expect(asSteamId()->set(castModel(), 'steam_id', str(STEAM_ID_64), []))->toBe(STEAM_ID_64);
});

it('serializes an object with __toString on set', function (): void {
    $value = new class
    {
        public function __toString(): string
        {
            return STEAM_ID_64;
        }
    };

    expect(asSteamId()->set(castModel(), 'steam_id', $value, []))->toBe(STEAM_ID_64);
});

it('throws when the value is a float on set', function (): void {
    asSteamId()->set(castModel(), 'steam_id', 1.5, []);
})->throws(InvalidSteamIdException::class, '"1.5" is not a valid 64-bit Steam ID.');

it('throws when the value is a boolean on set', function (): void {
    asSteamId()->set(castModel(), 'steam_id', true, []);
})->throws(InvalidSteamIdException::class, '"1" is not a valid 64-bit Steam ID.');

it('throws when the value is not a scalar on set', function (): void {
    asSteamId()->set(castModel(), 'steam_id', ['not', 'scalar'], []);
})->throws(InvalidSteamIdException::class, '"array" is not a valid 64-bit Steam ID.');

it('serializes a SteamId value object to its id 64 string on serialize', function (): void {
    $value = SteamId::fromSteamId64(STEAM_ID_64);

    expect(asSteamId()->serialize(castModel(), 'steam_id', $value, []))->toBe(STEAM_ID_64);
});

it('serializes a null value to null on serialize', function (): void {
    expect(asSteamId()->serialize(castModel(), 'steam_id', null, []))->toBeNull();
});

it('throws when the value is not a valid steam id 64 on serialize', function (): void {
    asSteamId()->serialize(castModel(), 'steam_id', 'not-a-steam-id', []);
})->throws(InvalidSteamIdException::class);

it('serializes the cast attribute to a flat string in toArray', function (): void {
    $model = castingModel()->setRawAttributes(['steam_id' => STEAM_ID_64]);

    expect($model->toArray())->toBe(['steam_id' => STEAM_ID_64]);
});

it('keeps a null cast attribute null in toArray', function (): void {
    $model = castingModel()->setRawAttributes(['steam_id' => null]);

    expect($model->toArray())->toBe(['steam_id' => null]);
});

it('serializes the cast attribute to a flat string in toJson', function (): void {
    $model = castingModel()->setRawAttributes(['steam_id' => STEAM_ID_64]);

    expect($model->toJson())->toBe(sprintf('{"steam_id":"%s"}', STEAM_ID_64));
});
