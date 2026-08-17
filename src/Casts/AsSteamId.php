<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Casts;

use Fkrzski\SteamApiSdk\Exceptions\InvalidSteamIdException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * @implements CastsAttributes<SteamId, SteamId|string|int|Stringable>
 */
final class AsSteamId implements CastsAttributes, SerializesCastableAttributes
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
     *
     * Wider than TSet on purpose — the guard below catches callers that ignore it.
     *
     * @phpstan-param mixed $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value) && ! $value instanceof Stringable) {
            throw new InvalidSteamIdException(sprintf('"%s" is not a valid 64-bit Steam ID.', get_debug_type($value)));
        }

        return SteamId::fromSteamId64((string) $value)->value;
    }

    /**
     * {@inheritDoc}
     *
     * `toArray()` unwraps an `Arrayable` only — a contract `SteamId` cannot implement.
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->set($model, $key, $value, $attributes);
    }
}
