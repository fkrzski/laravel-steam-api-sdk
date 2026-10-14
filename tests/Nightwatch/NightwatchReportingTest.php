<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Monitoring\NightwatchReporting;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Senders\SteamSender;
use GuzzleHttp\Handler\MockHandler;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Saloon\Http\Senders\GuzzleSender;

mutates(NightwatchReporting::class);

/**
 * The URLs Nightwatch records from here on, read after the bridge's redaction.
 *
 * @return ArrayObject<int, string>
 */
function nightwatchUrls(): ArrayObject
{
    /** @var ArrayObject<int, string> $urls */
    $urls = new ArrayObject;

    Nightwatch::redactOutgoingRequests(static function (OutgoingRequest $request) use ($urls): void {
        $urls[] = $request->url;
    });

    return $urls;
}

/**
 * Point the GuzzleSender the connector built on its own at the handler's queue instead of Steam.
 */
function defaultGuzzleSender(MockHandler $handler): GuzzleSender
{
    $sender = new ReflectionProperty(SteamSender::class, 'sender')->getValue(Steam::connector()->sender());

    if (! $sender instanceof GuzzleSender) {
        throw new LogicException('The connector does not send through a GuzzleSender of its own.');
    }

    $sender->getHandlerStack()->setHandler($handler);

    return $sender;
}

it('reports a steam request to nightwatch without its api key', function (): void {
    $urls = nightwatchUrls();
    defaultGuzzleSender(new MockHandler([vanityUrlAnswer()]));

    Steam::users()->resolveVanityUrl('gabe');

    expect($urls->getArrayCopy())->toBe([
        'https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?vanityurl=gabe',
    ]);
});

it('reports to nightwatch while it is enabled', function (mixed $enabled): void {
    config()->set('nightwatch.enabled', $enabled);

    expect(Steam::connector()->steamConfig->sender)->toBeInstanceOf(GuzzleSender::class);
})->with([
    'true' => true,
    'set to null' => null,
    'a truthy string' => '1',
]);

it('sends through the base sdk guzzle while nightwatch is disabled', function (mixed $enabled): void {
    config()->set('nightwatch.enabled', $enabled);

    expect(Steam::connector()->steamConfig->sender)->toBeNull();
})->with([
    'false' => false,
    'a falsy string' => '0',
    'blank' => '',
]);

it('sends through the base sdk guzzle without nightwatch', function (): void {
    app()->forgetInstance(Core::class);

    expect(Steam::connector()->steamConfig->sender)->toBeNull();
});

it('leaves a sender of your own without nightwatch', function (): void {
    $sender = bindGuzzleSender(new MockHandler([vanityUrlAnswer()]));
    $urls = nightwatchUrls();

    Steam::users()->resolveVanityUrl('gabe');

    expect(Steam::connector()->steamConfig->sender)->toBe($sender)
        ->and($urls->getArrayCopy())->toBeEmpty();
});

it('strips the api key from a sender of your own reporting to nightwatch', function (): void {
    bindGuzzleSender(new MockHandler([vanityUrlAnswer()]))
        ->addMiddleware(Nightwatch::guzzleMiddleware(), 'nightwatch');
    $urls = nightwatchUrls();

    Steam::users()->resolveVanityUrl('gabe');

    expect($urls->getArrayCopy())->toBe([
        'https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?vanityurl=gabe',
    ]);
});

it('leaves the urls of other hosts alone', function (): void {
    $request = new OutgoingRequest(
        method: 'GET',
        url: 'https://example.com/v1/?key=secret',
        duration: 0,
        requestSize: 0,
        responseSize: 0,
        statusCode: 200,
    );

    app(NightwatchReporting::class)->redactApiKey($request);

    expect($request->url)->toBe('https://example.com/v1/?key=secret');
});

it('reports nothing to nightwatch under the fake', function (): void {
    $urls = nightwatchUrls();
    fakeSteamEndpoints();

    Steam::users()->resolveVanityUrl('gabe');

    Steam::assertSent(ResolveVanityUrlRequest::class);
    expect($urls->getArrayCopy())->toBeEmpty();
});
