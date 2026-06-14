<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Casts;

use Fkrzski\SteamApiSdk\Exceptions\InvalidSteamIdException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<SteamId, SteamId|string>
 */
class AsSteamId implements CastsAttributes
{
    /**
     * {@inheritDoc}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?SteamId
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value)) {
            throw new InvalidSteamIdException(sprintf('"%s" is not a valid 64-bit Steam ID.', get_debug_type($value)));
        }

        return SteamId::fromSteamId64((string) $value);
    }

    /**
     * {@inheritDoc}
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SteamId) {
            return $value->value;
        }

        return SteamId::fromSteamId64($value)->value;
    }
}
