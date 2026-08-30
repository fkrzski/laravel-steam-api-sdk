<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

arch('laravel preset')->preset()->laravel();

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

// The facade is the one class that cannot satisfy the preset's protected-method
// rule: Laravel's Facade contract mandates a protected getFacadeAccessor(). The
// violation is reported without a line number, so it cannot be silenced with an
// @pest-arch-ignore comment — the whole class is excluded here and held to every
// other strict rule below.
arch('strict preset')->preset()->strict()->ignoring(Steam::class);

arch('strict preset, facade aside from its accessor')
    ->expect(Steam::class)
    ->toBeFinal()
    ->toUseStrictTypes()
    ->toUseStrictEquality();

// Callers catch the base SDK exception to handle every Steam failure in one
// place; a bridge exception outside that hierarchy would slip past them.
arch('package exceptions extend the base sdk exception')
    ->expect('Fkrzski\\LaravelSteamApiSdk\\Exceptions')
    ->toExtend(SteamApiException::class);

arch('contracts are interfaces')
    ->expect('Fkrzski\\LaravelSteamApiSdk\\Contracts')
    ->toBeInterfaces();
