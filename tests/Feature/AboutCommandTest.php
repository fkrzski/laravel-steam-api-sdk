<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Console\AboutSection;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamLanguageResolver;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Limit;

mutates(AboutSection::class);

/**
 * Run `php artisan about --json` and return the "Steam API" section.
 *
 * The command snake-cases section names without lowercasing them first, so
 * "Steam API" lands under "steam_a_p_i" in the JSON output.
 *
 * @return array<string, string>
 */
function steamAboutSection(): array
{
    Artisan::call('about', ['--json' => true]);

    /** @var array<string, array<string, string>> $output */
    $output = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    return $output['steam_a_p_i'] ?? [];
}

it('registers the Steam API section on the about command', function (): void {
    expect(steamAboutSection())->toHaveKeys([
        'api_key',
        'rate_limit_store',
        'daily_requests_remaining',
        'route_binding',
        'language',
        'timeouts',
        'retries',
    ]);
});

it('renders the section under its own heading', function (): void {
    Artisan::call('about', ['--only' => 'steam_api']);

    expect(Artisan::output())
        ->toContain('Steam API')
        ->toContain('API Key')
        ->toContain('Rate Limit Store')
        ->toContain('Daily Requests Remaining')
        ->toContain('Route Binding')
        ->toContain('Language')
        ->toContain('Timeouts')
        ->toContain('Retries')
        ->not->toContain('Application Name');
});

it('masks the api key', function (): void {
    expect(steamAboutSection()['api_key'])->toBe('********-key')
        ->not->toContain('test-steam-api-key');
});

it('masks a full length api key down to its last four characters', function (): void {
    config()->set('steam-api.key', '1234567890ABCDEF1234567890ABCDEF');

    expect(steamAboutSection()['api_key'])->toBe('********CDEF');
});

it('masks a short api key in full', function (): void {
    config()->set('steam-api.key', 'abcdefgh');

    expect(steamAboutSection()['api_key'])->toBe('********');
});

it('leaves the last four characters visible once the key outgrows the mask', function (): void {
    config()->set('steam-api.key', 'abcdefghi');

    expect(steamAboutSection()['api_key'])->toBe('********fghi');
});

it('masks the trimmed api key, matching the key the connector is built with', function (): void {
    config()->set('steam-api.key', '  test-steam-api-key  ');

    expect(steamAboutSection()['api_key'])->toBe('********-key');
});

it('reports a missing api key instead of masking nothing', function (mixed $key): void {
    config()->set('steam-api.key', $key);

    expect(steamAboutSection()['api_key'])->toBe('NOT SET');
})->with([
    'null' => null,
    'empty string' => '',
    'whitespace only' => '   ',
    'integer' => 123,
]);

it('reports the default cache store backing the rate limit', function (): void {
    config()->set('cache.default', 'file');

    expect(steamAboutSection()['rate_limit_store'])->toBe('file (default)');
});

it('reports an unknown cache store when the default store is not a name', function (): void {
    config()->set(['cache.default' => null]);

    expect(steamAboutSection()['rate_limit_store'])->toBe('UNKNOWN (default)');
});

it('names the configured rate limit store', function (string $store): void {
    config()->set([
        'cache.stores.steam' => ['driver' => 'array'],
        'steam-api.rate_limit.store' => $store,
    ]);

    expect(steamAboutSection()['rate_limit_store'])->toBe('steam');
})->with([
    'as written' => 'steam',
    'surrounded by whitespace' => '  steam  ',
]);

it('reports the default cache store for a blank rate limit store', function (string $store): void {
    config()->set('steam-api.rate_limit.store', $store);

    expect(steamAboutSection()['rate_limit_store'])->toBe('array (default)');
})->with([
    'empty string' => '',
    'whitespace only' => '   ',
]);

it('reports a rate limit store the cache config does not define as invalid', function (): void {
    config()->set('steam-api.rate_limit.store', 'redis-x');

    expect(steamAboutSection()['rate_limit_store'])->toBe('INVALID');
});

it('reports the untouched daily request budget', function (): void {
    expect(steamAboutSection()['daily_requests_remaining'])->toBe('100,000 of 100,000');
});

it('counts sent requests against the daily budget', function (): void {
    Steam::fake([
        ResolveVanityUrlRequest::class => MockResponse::make([
            'response' => ['success' => 1, 'steamid' => '76561198000000000'],
        ]),
    ]);

    Steam::resolveVanityUrl('gabelogannewell');

    expect(steamAboutSection()['daily_requests_remaining'])->toBe('99,999 of 100,000');
});

it('reports the budget as not metered when the api key is missing', function (mixed $key): void {
    config()->set(['steam-api.key' => $key]);

    expect(steamAboutSection()['daily_requests_remaining'])->toBe('not metered (no API key)');
})->with([
    'null' => null,
    'whitespace only' => '   ',
]);

it('reports an unknown budget when the connector declares no upfront limit', function (): void {
    app()->instance(SteamConnector::class, new class(new SteamConfig('test-steam-api-key')) extends SteamConnector
    {
        /**
         * @return array<Limit>
         */
        protected function resolveLimits(): array
        {
            return [];
        }
    });

    expect(steamAboutSection()['daily_requests_remaining'])->toBe('UNKNOWN');
});

it('reports the route binding as disabled by default', function (): void {
    expect(steamAboutSection()['route_binding'])->toBe('disabled');
});

it('names the parameter the binding claims once it is enabled', function (): void {
    config()->set('steam-api.route_binding.enabled', true);

    expect(steamAboutSection()['route_binding'])->toBe('enabled (steamId)');
});

it('names a custom claimed parameter', function (): void {
    config()->set([
        'steam-api.route_binding.enabled' => true,
        'steam-api.route_binding.parameter' => 'gamer',
    ]);

    expect(steamAboutSection()['route_binding'])->toBe('enabled (gamer)');
});

it('reports a truthy non-boolean as disabled, matching what the provider acts on', function (): void {
    config()->set('steam-api.route_binding.enabled', 1);

    expect(steamAboutSection()['route_binding'])->toBe('disabled');
});

it('reports an unknown parameter when the configured name is not a string', function (): void {
    config()->set([
        'steam-api.route_binding.enabled' => true,
        'steam-api.route_binding.parameter' => null,
    ]);

    expect(steamAboutSection()['route_binding'])->toBe('enabled (UNKNOWN)');
});

it('reports english as the language until told otherwise', function (): void {
    expect(steamAboutSection()['language'])->toBe('english (locale: en)');
});

it('names the configured language and says it came from config', function (): void {
    config()->set('steam-api.language', 'polish');

    expect(steamAboutSection()['language'])->toBe('polish (config)');
});

it('names the trimmed language, matching the one the connector is built with', function (): void {
    config()->set('steam-api.language', '  polish  ');

    expect(steamAboutSection()['language'])->toBe('polish (config)');
});

it('names the language it read from the locale, and the locale it read', function (): void {
    app()->setLocale('pt_BR');

    expect(steamAboutSection()['language'])->toBe('brazilian (locale: pt_BR)');
});

it('reports a locale steam publishes no language for as unset', function (): void {
    app()->setLocale('sw');

    expect(steamAboutSection()['language'])->toBe('NOT SET (locale: sw)');
});

it('reports the language the rebound resolver reads', function (): void {
    app()->bind(SteamLanguageResolver::class, fn (): SteamLanguageResolver => new class implements SteamLanguageResolver
    {
        public function __invoke(string $locale): Language
        {
            return Language::German;
        }
    });

    expect(steamAboutSection()['language'])->toBe('german (locale: en)');
});

it('reports a blanked out language as unset', function (mixed $language): void {
    app()->setLocale('pl');
    config()->set('steam-api.language', $language);

    expect(steamAboutSection()['language'])->toBe('NOT SET (config)');
})->with([
    'empty string' => '',
    'whitespace only' => '   ',
    'integer' => 123,
]);

it('reports a language steam does not know as invalid', function (): void {
    config()->set('steam-api.language', 'klingon');

    expect(steamAboutSection()['language'])->toBe('INVALID');
});

it('reports an unknown budget when the language is not a steam code', function (): void {
    config()->set('steam-api.language', 'klingon');

    expect(steamAboutSection()['daily_requests_remaining'])->toBe('UNKNOWN');
});

it('reports saloons timeouts as the default', function (): void {
    expect(steamAboutSection()['timeouts'])->toBe('connect 10s, request 30s (default)');
});

it('reports the configured timeouts', function (): void {
    config()->set([
        'steam-api.http.connect_timeout' => '0.5',
        'steam-api.http.request_timeout' => '60',
    ]);

    expect(steamAboutSection()['timeouts'])->toBe('connect 0.5s, request 60s');
});

it('reports a timeout of zero as unlimited', function (): void {
    config()->set('steam-api.http.connect_timeout', '0');

    expect(steamAboutSection()['timeouts'])->toBe('connect unlimited, request 30s');
});

it('drops the default once the request timeout alone is set', function (): void {
    config()->set('steam-api.http.request_timeout', '60');

    expect(steamAboutSection()['timeouts'])->toBe('connect 10s, request 60s');
});

it('reports a rejected timeout as invalid', function (string $option): void {
    config()->set('steam-api.http.'.$option, 'abc');

    expect(steamAboutSection()['timeouts'])->toBe('INVALID');
})->with([
    'connect' => 'connect_timeout',
    'request' => 'request_timeout',
]);

it('reports no retries as the default', function (): void {
    expect(steamAboutSection()['retries'])->toBe('none (default)');
});

it('reports the attempts and the pause between them', function (): void {
    config()->set([
        'steam-api.http.retry.tries' => '3',
        'steam-api.http.retry.interval' => '500',
    ]);

    expect(steamAboutSection()['retries'])->toBe('3 attempts, 500ms apart');
});

it('says the pause doubles with exponential backoff', function (): void {
    config()->set([
        'steam-api.http.retry.tries' => '3',
        'steam-api.http.retry.interval' => '500',
        'steam-api.http.retry.exponential_backoff' => true,
    ]);

    expect(steamAboutSection()['retries'])->toBe('3 attempts, 500ms apart, doubling');
});

it('reports attempts sent without a pause, backoff or not', function (bool $backoff): void {
    config()->set([
        'steam-api.http.retry.tries' => '3',
        'steam-api.http.retry.exponential_backoff' => $backoff,
    ]);

    expect(steamAboutSection()['retries'])->toBe('3 attempts, no pause');
})->with([
    'without backoff' => false,
    'with backoff' => true,
]);

it('drops the default for a pause set beside a single attempt', function (string $option, mixed $value): void {
    config()->set('steam-api.http.retry.'.$option, $value);

    expect(steamAboutSection()['retries'])->toBe('none');
})->with([
    'an interval' => ['interval', '500'],
    'exponential backoff' => ['exponential_backoff', true],
]);

it('reports a rejected retry option as invalid', function (string $option, mixed $value): void {
    config()->set('steam-api.http.retry.'.$option, $value);

    expect(steamAboutSection()['retries'])->toBe('INVALID');
})->with([
    'tries' => ['tries', '0'],
    'interval' => ['interval', '-1'],
    'exponential backoff' => ['exponential_backoff', 'maybe'],
]);

it('reports an unknown budget when an http option is rejected', function (): void {
    config()->set('steam-api.http.retry.tries', '0');

    expect(steamAboutSection()['daily_requests_remaining'])->toBe('UNKNOWN');
});

it('reports an unknown budget when the rate limit store is not defined', function (): void {
    config()->set('steam-api.rate_limit.store', 'redis-x');

    expect(steamAboutSection()['daily_requests_remaining'])->toBe('UNKNOWN');
});

it('does not resolve the connector while booting', function (): void {
    config()->set(['steam-api.key' => null]);

    expect(app()->resolved(SteamConnector::class))->toBeFalse();
});
