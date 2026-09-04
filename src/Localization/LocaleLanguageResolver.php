<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Localization;

use Fkrzski\LaravelSteamApiSdk\Contracts\SteamLanguageResolver;
use Fkrzski\SteamApiSdk\Enums\Language;

/**
 * Maps an application locale onto the language code Steam knows it by.
 *
 * The table cannot be mechanical: Valve spells its codes its own way, so `pl` is
 * `polish` but `ko` is `koreana` and `pt_BR` is `brazilian`.
 */
final class LocaleLanguageResolver implements SteamLanguageResolver
{
    /**
     * Keyed by locale, lowercased and underscored. Only the regions Steam itself
     * distinguishes are listed; the rest fall back to a shorter locale.
     *
     * @var array<string, Language>
     */
    private const array LANGUAGES = [
        'ar' => Language::Arabic,
        'bg' => Language::Bulgarian,
        'cs' => Language::Czech,
        'da' => Language::Danish,
        'de' => Language::German,
        'el' => Language::Greek,
        'en' => Language::English,
        'es' => Language::Spanish,
        'es_419' => Language::SpanishLatinAmerican,
        'es_ar' => Language::SpanishLatinAmerican,
        'es_cl' => Language::SpanishLatinAmerican,
        'es_co' => Language::SpanishLatinAmerican,
        'es_mx' => Language::SpanishLatinAmerican,
        'es_pe' => Language::SpanishLatinAmerican,
        'fi' => Language::Finnish,
        'fr' => Language::French,
        'hu' => Language::Hungarian,
        'id' => Language::Indonesian,
        'it' => Language::Italian,
        'ja' => Language::Japanese,
        'ko' => Language::Korean,
        'nb' => Language::Norwegian,
        'nl' => Language::Dutch,
        'nn' => Language::Norwegian,
        'no' => Language::Norwegian,
        'pl' => Language::Polish,
        'pt' => Language::Portuguese,
        'pt_br' => Language::PortugueseBrazil,
        'ro' => Language::Romanian,
        'ru' => Language::Russian,
        'sv' => Language::Swedish,
        'th' => Language::Thai,
        'tr' => Language::Turkish,
        'uk' => Language::Ukrainian,
        'vi' => Language::Vietnamese,
        'zh' => Language::ChineseSimplified,
        'zh_cn' => Language::ChineseSimplified,
        'zh_hans' => Language::ChineseSimplified,
        'zh_hant' => Language::ChineseTraditional,
        'zh_hk' => Language::ChineseTraditional,
        'zh_sg' => Language::ChineseSimplified,
        'zh_tw' => Language::ChineseTraditional,
    ];

    public function __invoke(string $locale): ?Language
    {
        $segments = explode('_', str_replace('-', '_', mb_strtolower(trim($locale))));

        while ($segments !== []) {
            $language = self::LANGUAGES[implode('_', $segments)] ?? null;

            if ($language instanceof Language) {
                return $language;
            }

            array_pop($segments);
        }

        return null;
    }
}
