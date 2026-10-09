<?php

declare(strict_types=1);

use Fkrzski\LaravelSteamApiSdk\Events\SteamRequestFailed;
use Fkrzski\LaravelSteamApiSdk\Events\SteamResponseReceived;
use Fkrzski\LaravelSteamApiSdk\Exceptions\InvalidSteamLoggingOptionException;
use Fkrzski\LaravelSteamApiSdk\Facades\Steam;
use Fkrzski\LaravelSteamApiSdk\Logging\LoggingOptions;
use Fkrzski\LaravelSteamApiSdk\Logging\SteamTrafficLogger;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\PlayerSummaryFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\SteamConnector;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Saloon\Http\Faking\MockResponse;

mutates(LoggingOptions::class, SteamTrafficLogger::class, InvalidSteamLoggingOptionException::class);

function loggingOptions(): LoggingOptions
{
    return app(LoggingOptions::class);
}

/**
 * Point the logging block at a `steam` channel and return the handler it writes to.
 */
function logSteamTraffic(bool $responses = false): TestHandler
{
    $handler = new TestHandler;

    config()->set([
        'logging.channels.steam' => [
            'driver' => 'custom',
            'via' => static fn (): Logger => new Logger('steam', [$handler]),
        ],
        'steam-api.logging.channel' => 'steam',
        'steam-api.logging.responses' => $responses,
    ]);

    return $handler;
}

/**
 * The level, message and context of every record, with durations masked as `Nms`,
 * since a faked call still takes real time.
 *
 * @return list<array{string, string, array<mixed>}>
 */
function steamLogRecords(TestHandler $handler): array
{
    return array_values(array_map(
        static fn (LogRecord $record): array => [
            $record->level->getName(),
            (string) preg_replace('/ in \d+ms,/', ' in Nms,', $record->message),
            $record->context,
        ],
        $handler->getRecords(),
    ));
}

it('logs nothing until a channel is set', function (): void {
    /** @var ArrayObject<int, string> $logged */
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, static function (MessageLogged $message) use ($logged): void {
        $logged[] = $message->message;
    });
    config()->set('steam-api.logging.responses', true);
    Steam::fake([MockResponse::make('', 500)]);

    expect(fn (): array => Steam::summaries([steamId()]))->toThrow(SteamApiException::class)
        ->and($logged->getArrayCopy())->toBeEmpty();
});

it('logs a failed call as a warning naming the exception and its code', function (): void {
    $handler = logSteamTraffic();
    Steam::fake([MockResponse::make('', 500)]);

    expect(fn (): array => Steam::summaries([steamId()]))->toThrow(SteamApiException::class)
        ->and(steamLogRecords($handler))->toBe([
            [
                'WARNING',
                'GetPlayerSummaries: Steam API request failed with HTTP 500.',
                ['exception' => SteamApiException::class, 'code' => 500],
            ],
        ]);
});

it('logs a call that never leaves as a warning alone', function (): void {
    $handler = logSteamTraffic(responses: true);
    config()->set('steam-api.key');
    fakeSteamEndpoints();

    try {
        Steam::summaries([steamId()]);
    } catch (ApiKeyNotConfiguredException $apiKeyNotConfiguredException) {
        expect(steamLogRecords($handler))->toBe([
            [
                'WARNING',
                $apiKeyNotConfiguredException->getMessage(),
                ['exception' => ApiKeyNotConfiguredException::class, 'code' => 0],
            ],
        ]);

        return;
    }

    throw new RuntimeException('Expected the lookup to fail.');
});

it('leaves responses out of the log until told otherwise', function (): void {
    $handler = logSteamTraffic();
    fakeSteamEndpoints();

    Steam::summaries([steamId()]);

    expect($handler->getRecords())->toBeEmpty();
});

it('logs every response at debug with the key-free query in context', function (): void {
    config()->set('steam-api.http.retry.tries', 2);
    $handler = logSteamTraffic(responses: true);
    Steam::fake([
        MockResponse::make('', 500),
        SteamResponse::playerSummaries(PlayerSummaryFactory::new()),
    ]);

    Steam::summaries([steamId()]);

    expect(steamLogRecords($handler))->toBe([
        ['DEBUG', 'ISteamUser/GetPlayerSummaries/v2: HTTP 500 in Nms, attempt 1', ['query' => ['steamids' => '76561198000000000']]],
        ['DEBUG', 'ISteamUser/GetPlayerSummaries/v2: HTTP 200 in Nms, attempt 2', ['query' => ['steamids' => '76561198000000000']]],
    ]);
});

it('logs a 429 before the rate limit failure it raises', function (): void {
    $handler = logSteamTraffic(responses: true);
    Steam::fake([MockResponse::make('', 429)]);

    try {
        Steam::summaries([steamId()]);
    } catch (SteamRateLimitException $steamRateLimitException) {
        expect(steamLogRecords($handler))->toBe([
            ['DEBUG', 'ISteamUser/GetPlayerSummaries/v2: HTTP 429 in Nms, attempt 1', ['query' => ['steamids' => '76561198000000000']]],
            [
                'WARNING',
                $steamRateLimitException->getMessage(),
                ['exception' => SteamRateLimitException::class, 'code' => 0],
            ],
        ]);

        return;
    }

    throw new RuntimeException('Expected the lookup to fail.');
});

it('writes the duration in whole milliseconds', function (): void {
    $handler = logSteamTraffic(responses: true);

    event(new SteamResponseReceived('ISteamUser/GetPlayerSummaries/v2', ['steamids' => '76561198000000000'], 2, 503, 1.2346));

    expect($handler->getRecords()[0]->message ?? null)
        ->toBe('ISteamUser/GetPlayerSummaries/v2: HTTP 503 in 1235ms, attempt 2');
});

it('is silenced along with the events by Event::fake()', function (array $events): void {
    $handler = logSteamTraffic(responses: true);
    Event::fake($events);
    Steam::fake([MockResponse::make('', 500)]);

    expect(fn (): array => Steam::summaries([steamId()]))->toThrow(SteamApiException::class)
        ->and($handler->getRecords())->toBeEmpty();
})->with([
    'every event' => [[]],
    'the steam events' => [[SteamResponseReceived::class, SteamRequestFailed::class]],
]);

it('reads the trimmed channel name', function (): void {
    logSteamTraffic();
    config()->set('steam-api.logging.channel', '  steam  ');

    expect(loggingOptions()->channel())->toBe('steam');
});

it('logs nothing for a channel left unset', function (mixed $channel): void {
    config()->set('steam-api.logging.channel', $channel);

    expect(loggingOptions()->channel())->toBeNull();
})->with([
    'null' => null,
    'empty string' => '',
    'whitespace only' => '   ',
]);

it('logs nothing when the published config empties the block', function (): void {
    config()->set('steam-api.logging', []);

    expect(loggingOptions()->channel())->toBeNull()
        ->and(loggingOptions()->responses())->toBeFalse();
});

it('reads responses however it is written', function (mixed $value, bool $enabled): void {
    config()->set('steam-api.logging.responses', $value);

    expect(loggingOptions()->responses())->toBe($enabled);
})->with([
    'true' => [true, true],
    'the string true' => ['true', true],
    'one' => ['1', true],
    'on' => ['on', true],
    'false' => [false, false],
    'the string false' => ['false', false],
    'zero' => ['0', false],
    'empty string' => ['', false],
    'null' => [null, false],
]);

it('throws when the logging config defines no such channel', function (mixed $channel): void {
    config()->set('steam-api.logging.channel', $channel);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamLoggingOptionException::class);
})->with([
    'an undefined name' => 'papertrail-x',
    'a number' => 123,
    'a boolean' => true,
    'an array' => [[]],
]);

it('throws when responses is not a boolean, even with no channel set', function (mixed $value): void {
    config()->set('steam-api.logging.responses', $value);

    expect(fn (): SteamConnector => app(SteamConnector::class))
        ->toThrow(InvalidSteamLoggingOptionException::class);
})->with([
    'a word' => 'maybe',
    'a number' => 2,
]);

it('names the config key, the env var and the rejected channel', function (): void {
    expect(new InvalidSteamLoggingOptionException('channel', 'papertrail-x'))->getMessage()->toBe(
        'The configured Steam logging option "steam-api.logging.channel" cannot be "papertrail-x". Set '
        .'STEAM_API_LOG_CHANNEL in your .env file, or that config value, to a channel name from '
        .'"logging.channels", or leave it unset to log nothing.',
    );
});

it('names the config key, the env var and the rejected responses value', function (): void {
    expect(new InvalidSteamLoggingOptionException('responses', 'maybe'))->getMessage()->toBe(
        'The configured Steam logging option "steam-api.logging.responses" cannot be "maybe". Set '
        .'STEAM_API_LOG_RESPONSES in your .env file, or that config value, to true or false.',
    );
});
