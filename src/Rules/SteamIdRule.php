<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Rules;

use Closure;
use Fkrzski\SteamApiSdk\Exceptions\InvalidSteamIdException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Contracts\Validation\ValidationRule;
use Stringable;

/**
 * Validates that a value resolves to a {@see SteamId}.
 *
 * By default the rule mirrors the route binding: a 64-bit ID or a
 * "/profiles/<id>" URL passes. Call {@see self::strict()} to accept only a raw
 * 64-bit ID. The rule never touches the network — it validates format alone.
 */
final class SteamIdRule implements ValidationRule
{
    private bool $strict = false;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Accept only a raw 64-bit Steam ID, rejecting profile URLs.
     */
    public function strict(): self
    {
        $this->strict = true;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $input = match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            $value instanceof Stringable => (string) $value,
            default => null,
        };

        if ($input === null || ! $this->resolves(trim($input))) {
            $fail($this->messageKey())->translate();
        }
    }

    /**
     * Whether the trimmed input resolves to a Steam ID under the active mode.
     */
    private function resolves(string $input): bool
    {
        if (! $this->strict) {
            return SteamId::tryFromInput($input) instanceof SteamId;
        }

        try {
            SteamId::fromSteamId64($input);
        } catch (InvalidSteamIdException) {
            return false;
        }

        return true;
    }

    private function messageKey(): string
    {
        return $this->strict
            ? 'steam-api::validation.steam_id_strict'
            : 'steam-api::validation.steam_id';
    }
}
