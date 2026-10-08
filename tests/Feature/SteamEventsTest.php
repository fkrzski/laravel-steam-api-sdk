<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestFailed;
use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestSending;
use Fkrzski\LaravelSteamApiSdk\Events\SteamResponseReceived;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Illuminate\Support\Facades\Event;
use Saloon\Http\Faking\MockResponse;

const STEAM_TRAFFIC_EVENTS = [SteamRequestSending::class, SteamResponseReceived::class, SteamRequestFailed::class];

/**
 * Every Steam traffic event, in the order the dispatcher saw it.
 *
 * @return ArrayObject<int, SteamRequestSending|SteamResponseReceived|SteamRequestFailed>
 */
function recordSteamTraffic(): ArrayObject
{
    /** @var ArrayObject<int, SteamRequestSending|SteamResponseReceived|SteamRequestFailed> $events */
    $events = new ArrayObject;

    Event::listen(
        STEAM_TRAFFIC_EVENTS,
        static function (SteamRequestSending|SteamResponseReceived|SteamRequestFailed $event) use ($events): void {
            $events[] = $event;
        },
    );

    return $events;
}

/**
 * @param  ArrayObject<int, SteamRequestSending|SteamResponseReceived|SteamRequestFailed>  $events
 * @return list<string>
 */
function steamTraffic(ArrayObject $events): array
{
    return array_values(array_map(
        static fn (SteamRequestSending|SteamResponseReceived|SteamRequestFailed $event): string => match (true) {
            $event instanceof SteamRequestSending => sprintf('sending %s #%d', $event->method, $event->attempt),
            $event instanceof SteamResponseReceived => sprintf('received %d #%d', $event->status, $event->attempt),
            default => 'failed '.$event->exception::class,
        },
        $events->getArrayCopy(),
    ));
}

it('dispatches a sending and a received event for every attempt', function (): void {
    config()->set('steam-api.http.retry.tries', 2);
    $events = recordSteamTraffic();
    Steam::fake([
        MockResponse::make('', 500),
        SteamResponse::playerSummaries(PlayerSummaryFactory::new()),
    ]);

    Steam::summaries([steamId()]);

    expect(steamTraffic($events))->toBe([
        'sending ISteamUser/GetPlayerSummaries/v2 #1',
        'received 500 #1',
        'sending ISteamUser/GetPlayerSummaries/v2 #2',
        'received 200 #2',
    ]);
});

it('copies the method, the key-free query and the timing onto the events', function (): void {
    Event::fake(STEAM_TRAFFIC_EVENTS);
    fakeSteamEndpoints();

    Steam::summaries([steamId()]);

    Event::assertDispatched(
        SteamRequestSending::class,
        static fn (SteamRequestSending $event): bool => $event->method === 'ISteamUser/GetPlayerSummaries/v2'
            && $event->query === ['steamids' => '76561198000000000']
            && $event->attempt === 1,
    );
    Event::assertDispatched(
        SteamResponseReceived::class,
        static fn (SteamResponseReceived $event): bool => $event->method === 'ISteamUser/GetPlayerSummaries/v2'
            && $event->query === ['steamids' => '76561198000000000']
            && $event->attempt === 1
            && $event->status === 200
            && $event->duration >= 0.0
            && $event->duration < 1.0,
    );
    Event::assertNotDispatched(SteamRequestFailed::class);
});

it('dispatches one failure carrying the exception the caller gets', function (): void {
    Event::fake(STEAM_TRAFFIC_EVENTS);
    Steam::fake([MockResponse::make('', 500)]);

    try {
        Steam::summaries([steamId()]);
    } catch (SteamApiException $steamApiException) {
        Event::assertDispatchedTimes(SteamRequestFailed::class, 1);
        Event::assertDispatched(
            SteamRequestFailed::class,
            static fn (SteamRequestFailed $event): bool => $event->exception === $steamApiException,
        );

        return;
    }

    throw new RuntimeException('Expected the lookup to fail.');
});

it('receives a 429 before it fails as a rate limit', function (): void {
    $events = recordSteamTraffic();
    Steam::fake([MockResponse::make('', 429)]);

    expect(fn (): array => Steam::summaries([steamId()]))->toThrow(SteamRateLimitException::class)
        ->and(steamTraffic($events))->toBe([
            'sending ISteamUser/GetPlayerSummaries/v2 #1',
            'received 429 #1',
            'failed '.SteamRateLimitException::class,
        ]);
});

it('dispatches only the failure for a call that never leaves', function (Closure $refuse, string $exception): void {
    $events = recordSteamTraffic();
    $refuse();
    fakeSteamEndpoints();

    expect(fn (): array => Steam::summaries([steamId()]))->toThrow($exception)
        ->and(steamTraffic($events))->toBe(['failed '.$exception]);
})->with([
    'no api key' => [
        static function (): void {
            config()->set('steam-api.key');
        },
        ApiKeyNotConfiguredException::class,
    ],
    'a spent daily budget' => [
        spendDailyBudget(...),
        SteamRateLimitException::class,
    ],
]);

it('fails the call before it is sent when a sending listener throws', function (): void {
    $events = recordSteamTraffic();
    Event::listen(SteamRequestSending::class, static function (): never {
        throw new RuntimeException('The listener failed.');
    });
    fakeSteamEndpoints();

    expect(fn (): array => Steam::summaries([steamId()]))->toThrow(RuntimeException::class, 'The listener failed.')
        ->and(steamTraffic($events))->toBe([
            'sending ISteamUser/GetPlayerSummaries/v2 #1',
            'failed '.RuntimeException::class,
        ]);

    Steam::assertNotSent(GetPlayerSummariesRequest::class);
});

it('drops a throw from a failure listener so the caller still gets the steam failure', function (): void {
    Event::listen(SteamRequestFailed::class, static function (): never {
        throw new RuntimeException('The listener failed.');
    });
    Steam::fake([MockResponse::make('', 500)]);

    expect(fn (): array => Steam::summaries([steamId()]))->toThrow(SteamApiException::class, 'HTTP 500');
});

it('reaches a dispatcher faked after the connector was built', function (): void {
    fakeSteamEndpoints();
    Event::fake(STEAM_TRAFFIC_EVENTS);

    Steam::summaries([steamId()]);

    Event::assertDispatched(SteamRequestSending::class);
    Event::assertDispatched(SteamResponseReceived::class);
});

it('keeps dispatching from the connector built once scoped instances are flushed', function (): void {
    $connector = Steam::connector();
    app()->forgetScopedInstances();
    Event::fake(STEAM_TRAFFIC_EVENTS);
    fakeSteamEndpoints();

    Steam::summaries([steamId()]);

    expect(Steam::connector())->not->toBe($connector);
    Event::assertDispatched(SteamRequestSending::class);
    Event::assertDispatched(SteamResponseReceived::class);
});
