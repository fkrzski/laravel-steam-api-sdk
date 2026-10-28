<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Monitoring;

use GuzzleHttp\Psr7\Uri;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Saloon\Http\Senders\GuzzleSender;

/**
 * Reports Steam traffic to Laravel Nightwatch.
 *
 * `saloonphp/laravel-plugin` only attaches Nightwatch's middleware to a bare
 * GuzzleSender, and the base SDK wraps every sender in its own, so the bridge
 * attaches it instead. The provider picks the sender through this class and
 * `about` reports it the same way, so the two cannot disagree.
 *
 * @internal
 */
final readonly class NightwatchReporting
{
    private const string STEAM_HOST = 'api.steampowered.com';

    public function __construct(
        private Application $app,
        private ConfigRepository $config,
    ) {}

    /**
     * Whether Nightwatch's provider has registered, which the facade cannot work without.
     */
    public function installed(): bool
    {
        return $this->app->bound(Core::class);
    }

    /**
     * Nightwatch binds its core while disabled too, and then hooks nothing onto
     * Laravel's own HTTP client, so the default sender carries nothing either.
     * The option is read the way Nightwatch reads it, null included.
     */
    public function enabled(): bool
    {
        return $this->installed() && ($this->config->get('nightwatch.enabled') ?? true);
    }

    public function sender(): GuzzleSender
    {
        return new GuzzleSender()->addMiddleware(Nightwatch::guzzleMiddleware(), 'nightwatch');
    }

    /**
     * Steam takes the key as a query parameter, and Nightwatch records the whole URL.
     */
    public function redactApiKey(OutgoingRequest $request): void
    {
        $uri = new Uri($request->url);

        if ($uri->getHost() === self::STEAM_HOST) {
            $request->url = (string) Uri::withoutQueryValue($uri, 'key');
        }
    }
}
