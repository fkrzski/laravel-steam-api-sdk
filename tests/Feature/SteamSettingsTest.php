<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Contracts\SteamLanguageResolver;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamConfigException;
use Fkrzski\LaravelSteamApiSdk\SteamSettings;
use Fkrzski\SteamApiSdk\Enums\Language;
use Illuminate\Support\Arr;
use Saloon\Http\Senders\GuzzleSender;

mutates(SteamSettings::class);

it('reads the trimmed api key', function (): void {
    config()->set('steam-api.key', '  test-steam-api-key  ');

    expect(steamSettings()->apiKey())->toBe('test-steam-api-key');
});

it('has no api key while it is unset, blank or not a string', function (mixed $key): void {
    config()->set('steam-api.key', $key);

    expect(steamSettings()->apiKey())->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'whitespace only' => ['   '],
    'integer' => [123],
    'array' => [[]],
    'boolean' => [true],
]);

it('follows the locale until a language is configured', function (): void {
    app()->setLocale('pt_BR');

    expect(steamSettings())
        ->languageFollowsLocale()->toBeTrue()
        ->language()->toBe(Language::PortugueseBrazil);
});

it('follows the locale when the published config drops the language', function (): void {
    config()->set('steam-api', Arr::except((array) config('steam-api'), 'language'));

    expect(steamSettings())
        ->languageFollowsLocale()->toBeTrue()
        ->language()->toBe(Language::English);
});

it('sends no language for a locale steam publishes none for', function (): void {
    app()->setLocale('sw');

    expect(steamSettings())
        ->languageFollowsLocale()->toBeTrue()
        ->language()->toBeNull();
});

it('reads the locale through the rebound resolver', function (): void {
    app()->bind(SteamLanguageResolver::class, fn (): SteamLanguageResolver => new class implements SteamLanguageResolver
    {
        public function __invoke(string $locale): Language
        {
            return Language::German;
        }
    });

    expect(steamSettings()->language())->toBe(Language::German);
});

it('takes the configured language over the locale', function (string $language): void {
    app()->setLocale('de');
    config()->set('steam-api.language', $language);

    expect(steamSettings())
        ->languageFollowsLocale()->toBeFalse()
        ->language()->toBe(Language::Polish);
})->with([
    'as written' => 'polish',
    'surrounded by whitespace' => '  polish  ',
]);

it('sends no language once the configured one is blanked out', function (mixed $language): void {
    config()->set('steam-api.language', $language);

    expect(steamSettings())
        ->languageFollowsLocale()->toBeFalse()
        ->language()->toBeNull();
})->with([
    'empty string' => [''],
    'whitespace only' => ['   '],
    'integer' => [123],
    'false' => [false],
    'true' => [true],
    'array' => [[]],
]);

it('throws when the configured language is not one of steams codes', function (string $language): void {
    config()->set('steam-api.language', $language);

    expect(fn (): ?Language => steamSettings()->language())
        ->toThrow(InvalidSteamConfigException::class, '"'.$language.'"');
})->with([
    'unknown' => 'klingon',
    'iso code' => 'pl',
    'capitalised' => 'Polish',
]);

it('leaves route binding off until the application opts in', function (): void {
    expect(steamSettings()->routeParameter())->toBeNull();
});

it('turns route binding on for a literal true only', function (mixed $enabled): void {
    config()->set('steam-api.route_binding.enabled', $enabled);

    expect(steamSettings()->routeParameter())->toBeNull();
})->with([
    'one' => [1],
    'the string true' => ['true'],
    'the string on' => ['on'],
]);

it('leaves route binding off when the published config drops the enabled key', function (): void {
    config()->set('steam-api.route_binding', ['parameter' => 'steamId']);

    expect(steamSettings()->routeParameter())->toBeNull();
});

it('claims steamId once route binding is enabled', function (): void {
    config()->set('steam-api.route_binding.enabled', true);

    expect(steamSettings()->routeParameter())->toBe('steamId');
});

it('claims the configured parameter', function (string $parameter): void {
    config()->set([
        'steam-api.route_binding.enabled' => true,
        'steam-api.route_binding.parameter' => $parameter,
    ]);

    expect(steamSettings()->routeParameter())->toBe('gamer');
})->with([
    'as written' => 'gamer',
    'surrounded by whitespace' => '  gamer  ',
]);

it('claims steamId for a parameter left unset', function (mixed $parameter): void {
    config()->set([
        'steam-api.route_binding.enabled' => true,
        'steam-api.route_binding.parameter' => $parameter,
    ]);

    expect(steamSettings()->routeParameter())->toBe('steamId');
})->with([
    'null' => [null],
    'whitespace only' => ['   '],
    'not a string' => [5],
]);

it('claims steamId when the published config drops the parameter', function (): void {
    config()->set('steam-api.route_binding', ['enabled' => true]);

    expect(steamSettings()->routeParameter())->toBe('steamId');
});

it('renders exceptions until told otherwise', function (): void {
    expect(steamSettings()->rendersExceptions())->toBeTrue();
});

it('stops rendering exceptions for a literal false only', function (mixed $render, bool $renders): void {
    config()->set('steam-api.exceptions.render', $render);

    expect(steamSettings()->rendersExceptions())->toBe($renders);
})->with([
    'false' => [false, false],
    'zero' => [0, true],
    'the string false' => ['false', true],
    'null' => [null, true],
]);

it('renders exceptions when the published config drops the block', function (): void {
    config()->set('steam-api', Arr::except((array) config('steam-api'), 'exceptions'));

    expect(steamSettings()->rendersExceptions())->toBeTrue();
});

it('sends through the base sdk sender without nightwatch', function (mixed $sender): void {
    config()->set('steam-api.http.sender', $sender);

    expect(steamSettings()->sendsThroughNightwatch())->toBeFalse();
})->with([
    'unset' => [null],
    'a sender of your own' => [GuzzleSender::class],
]);
