<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Rules\SteamIdRule;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Stringable;

mutates(SteamIdRule::class);

const RULE_STEAM_ID_64 = '76561198000000000';

function validateSteamId(mixed $value, ?SteamIdRule $rule = null): ValidatorContract
{
    return Validator::make(
        ['steam_id' => $value],
        ['steam_id' => [$rule ?? SteamIdRule::make()]],
    );
}

it('passes for a 64-bit steam id', function (): void {
    expect(validateSteamId(RULE_STEAM_ID_64)->passes())->toBeTrue();
});

it('passes for a profile url', function (): void {
    expect(validateSteamId('https://steamcommunity.com/profiles/'.RULE_STEAM_ID_64)->passes())->toBeTrue();
});

it('passes for a profile url with a trailing slash', function (): void {
    expect(validateSteamId('https://steamcommunity.com/profiles/'.RULE_STEAM_ID_64.'/')->passes())->toBeTrue();
});

it('passes for a value padded with whitespace', function (): void {
    expect(validateSteamId('  '.RULE_STEAM_ID_64.'  ')->passes())->toBeTrue();
});

it('passes for an integer steam id', function (): void {
    expect(validateSteamId(76561198000000000)->passes())->toBeTrue();
});

it('passes for a SteamId value object', function (): void {
    expect(validateSteamId(SteamId::fromSteamId64(RULE_STEAM_ID_64))->passes())->toBeTrue();
});

it('passes for a stringable value', function (): void {
    expect(validateSteamId(new Stringable(RULE_STEAM_ID_64))->passes())->toBeTrue();
});

it('fails for an unresolvable string', function (): void {
    expect(validateSteamId('not-a-steam-id')->fails())->toBeTrue();
});

it('fails for a vanity url', function (): void {
    expect(validateSteamId('https://steamcommunity.com/id/gabelogannewell')->fails())->toBeTrue();
});

it('fails for a number that is too short', function (): void {
    expect(validateSteamId('7656119800000000')->fails())->toBeTrue();
});

it('fails for a number that is too long', function (): void {
    expect(validateSteamId('765611980000000000')->fails())->toBeTrue();
});

it('fails for null', function (): void {
    expect(validateSteamId(null)->fails())->toBeTrue();
});

it('fails for an array', function (): void {
    expect(validateSteamId([RULE_STEAM_ID_64])->fails())->toBeTrue();
});

it('fails for a float', function (): void {
    expect(validateSteamId(76561198000000000.0)->fails())->toBeTrue();
});

it('fails for an object that is not stringable', function (): void {
    expect(validateSteamId(new stdClass)->fails())->toBeTrue();
});

it('passes for a 64-bit steam id in strict mode', function (): void {
    expect(validateSteamId(RULE_STEAM_ID_64, SteamIdRule::make()->strict())->passes())->toBeTrue();
});

it('passes for a value padded with whitespace in strict mode', function (): void {
    expect(validateSteamId('  '.RULE_STEAM_ID_64.'  ', SteamIdRule::make()->strict())->passes())->toBeTrue();
});

it('fails for a profile url in strict mode', function (): void {
    expect(validateSteamId('https://steamcommunity.com/profiles/'.RULE_STEAM_ID_64, SteamIdRule::make()->strict())->fails())->toBeTrue();
});

it('fails for an unresolvable string in strict mode', function (): void {
    expect(validateSteamId('not-a-steam-id', SteamIdRule::make()->strict())->fails())->toBeTrue();
});

it('reports the package translation for the default mode', function (): void {
    $errors = validateSteamId('not-a-steam-id')->errors();

    expect($errors->first('steam_id'))
        ->toBe('The steam id field must be a valid Steam ID or profile URL.');
});

it('reports the package translation for strict mode', function (): void {
    $errors = validateSteamId('not-a-steam-id', SteamIdRule::make()->strict())->errors();

    expect($errors->first('steam_id'))
        ->toBe('The steam id field must be a valid 64-bit Steam ID.');
});

it('returns the same instance when switching to strict mode', function (): void {
    $rule = SteamIdRule::make();

    expect($rule->strict())->toBe($rule);
});
