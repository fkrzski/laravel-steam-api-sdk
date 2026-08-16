<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Console\AboutSection;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
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

it('reports the cache store backing the rate limit', function (): void {
    config()->set('cache.default', 'file');

    expect(steamAboutSection()['rate_limit_store'])->toBe('file');
});

it('reports an unknown cache store when the default store is not a name', function (): void {
    config()->set(['cache.default' => null]);

    expect(steamAboutSection()['rate_limit_store'])->toBe('UNKNOWN');
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

it('reports an unknown budget when the api key is missing', function (): void {
    config()->set(['steam-api.key' => null]);

    expect(steamAboutSection()['daily_requests_remaining'])->toBe('UNKNOWN');
});

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

it('does not resolve the connector while booting', function (): void {
    config()->set(['steam-api.key' => null]);

    expect(app()->resolved(SteamConnector::class))->toBeFalse();
});
