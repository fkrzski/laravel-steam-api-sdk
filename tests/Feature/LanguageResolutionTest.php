<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Contracts\SteamLanguageResolver;
use Fkrzski\LaravelSteamApiSdk\Localization\LocaleLanguageResolver;
use Fkrzski\SteamApiSdk\Enums\Language;

mutates(LocaleLanguageResolver::class);

function resolver(): LocaleLanguageResolver
{
    return new LocaleLanguageResolver;
}

it('resolves a locale to the code steam knows the language by', function (string $locale, Language $language): void {
    expect(resolver()($locale))->toBe($language);
})->with([
    'a code steam spells the same way' => ['pl', Language::Polish],
    'a code steam spells its own way' => ['ko', Language::Korean],
    'a region steam publishes on its own' => ['pt_BR', Language::PortugueseBrazil],
    'the language that region belongs to' => ['pt', Language::Portuguese],
    'a script steam publishes on its own' => ['zh_Hant', Language::ChineseTraditional],
    'latin american spanish' => ['es_419', Language::SpanishLatinAmerican],
]);

it('reads a locale however the application spells it', function (string $locale): void {
    expect(resolver()($locale))->toBe(Language::PortugueseBrazil);
})->with([
    'underscore' => 'pt_BR',
    'hyphen' => 'pt-BR',
    'lowercase' => 'pt_br',
    'uppercase' => 'PT_BR',
    'surrounded by whitespace' => '  pt_BR  ',
]);

it('falls back to the locale a segment shorter', function (string $locale, Language $language): void {
    expect(resolver()($locale))->toBe($language);
})->with([
    'a region steam does not separate' => ['en_GB', Language::English],
    'a region below a script it does' => ['zh_Hant_TW', Language::ChineseTraditional],
    'a region below one it does not' => ['de_DE_1996', Language::German],
]);

it('resolves a locale steam publishes no language for to none', function (string $locale): void {
    expect(resolver()($locale))->toBeNull();
})->with([
    'a language steam does not publish' => 'sw',
    'nonsense' => 'not-a-locale',
    'empty' => '',
    'whitespace only' => '   ',
]);

it('is the resolver the container hands out', function (): void {
    expect(app(SteamLanguageResolver::class))->toBeInstanceOf(LocaleLanguageResolver::class);
});
