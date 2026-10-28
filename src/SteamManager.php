<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk;

use Closure;
use DateTimeInterface;
use Fkrzski\LaravelSteamApiSdk\Contracts\SteamManager as SteamManagerContract;
use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeNotInstalledException;
use Fkrzski\LaravelSteamApiSdk\Exceptions\FakeOutsideTestsException;
use Fkrzski\SteamApiSdk\Dto\NewsItem;
use Fkrzski\SteamApiSdk\Http\Resources\AppsResource;
use Fkrzski\SteamApiSdk\Http\Resources\NewsResource;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\Http\Resources\WebApiResource;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Generator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Pool;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Laravel-friendly wrapper around the framework-agnostic {@see SteamConnector}.
 *
 * Resolves the connector through the container that built this manager, so a
 * long-lived worker never hands one request's connector to the next.
 */
final readonly class SteamManager implements SteamManagerContract
{
    /**
     * @internal
     *
     * @param  Closure(): SteamConnector  $connectorResolver
     */
    public function __construct(
        private Closure $connectorResolver,
        private Application $app,
    ) {}

    /**
     * The underlying Saloon connector. Use this as an escape hatch for
     * advanced features (e.g. building custom requests) not exposed here.
     */
    public function connector(): SteamConnector
    {
        return ($this->connectorResolver)();
    }

    /**
     * Send a Steam request and return the raw Saloon response.
     */
    public function send(Request $request): Response
    {
        return $this->connector()->send($request);
    }

    /**
     * Build a request pool for sending Steam requests concurrently.
     *
     * @param  iterable<Request>|callable  $requests
     */
    public function pool(
        iterable|callable $requests = [],
        int|callable $concurrency = 5,
        ?callable $responseHandler = null,
        ?callable $exceptionHandler = null,
    ): Pool {
        return $this->connector()->pool($requests, $concurrency, $responseHandler, $exceptionHandler);
    }

    /**
     * The IPlayerService endpoints, reached fluently.
     *
     * Built by the connector rather than held here, so the resource always sends
     * through the connector this manager resolves right now — the one a fake sits on.
     */
    public function players(): PlayersResource
    {
        return $this->connector()->players();
    }

    /**
     * The ISteamUser endpoints, reached fluently.
     */
    public function users(): UsersResource
    {
        return $this->connector()->users();
    }

    /**
     * The ISteamUserStats endpoints, reached fluently.
     */
    public function stats(): StatsResource
    {
        return $this->connector()->stats();
    }

    /**
     * The ISteamApps endpoints, reached fluently.
     */
    public function apps(): AppsResource
    {
        return $this->connector()->apps();
    }

    /**
     * The ISteamNews endpoint, reached fluently.
     */
    public function news(): NewsResource
    {
        return $this->connector()->news();
    }

    /**
     * The ISteamWebAPIUtil endpoints, reached fluently.
     */
    public function webApi(): WebApiResource
    {
        return $this->connector()->webApi();
    }

    /**
     * Walk an app's news lazily, newest first, one request per page.
     *
     * Each page asks for news up to the last item's date, which Steam counts
     * inclusively, so items already yielded are skipped by ID. The feed ends on a
     * page holding all that remains or bringing nothing new, which is also where
     * it stops when `perPage` or more items share one second.
     *
     * @param  list<string>  $feeds
     * @param  list<string>  $tags
     * @return LazyCollection<int, NewsItem>
     *
     * @throws InvalidArgumentException when `perPage` is below 2
     */
    public function newsFeed(
        int $appId,
        int $perPage = 20,
        ?int $maxLength = null,
        ?DateTimeInterface $endDate = null,
        array $feeds = [],
        array $tags = [],
    ): LazyCollection {
        if ($perPage < 2) {
            throw new InvalidArgumentException(
                'newsFeed() needs a page of at least 2 items: every page after the first opens with the last item of the one before.',
            );
        }

        return LazyCollection::make(function () use ($appId, $perPage, $maxLength, $endDate, $feeds, $tags): Generator {
            $seen = [];

            do {
                $page = $this->news()->appNews($appId, $perPage, $maxLength, $endDate, $feeds, $tags);
                $fresh = false;

                foreach ($page->items as $item) {
                    if ($seen[$item->id] ?? false) {
                        continue;
                    }

                    $seen[$item->id] = true;
                    $fresh = true;
                    $endDate = $item->publishedAt;

                    yield $item;
                }
            } while ($fresh && count($page->items) < $page->total);
        });
    }

    /**
     * Swap the connector's HTTP client for a Saloon mock, returning it for assertions.
     *
     * Nothing detaches the mock, so faking is refused outside tests. A faked
     * retry keeps its tries but not the pause between them.
     *
     * @param  array<array-key, (callable(PendingRequest): mixed)|Fixture|MockResponse>  $responses
     *
     * @throws FakeOutsideTestsException when the application is not running tests
     */
    public function fake(array $responses = []): SteamFake
    {
        if (! $this->app->runningUnitTests()) {
            throw new FakeOutsideTestsException;
        }

        $mockClient = new SteamFake($responses);

        $connector = $this->connector();
        $connector->withMockClient($mockClient);

        // Saloon pauses with a bare usleep(), which Sleep::fake() cannot reach.
        $connector->retryInterval = 0;

        return $mockClient;
    }

    /**
     * Assert that a request matching the class name, URL pattern or closure was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSent(string|callable $value): void
    {
        $this->mockClient()->assertSent($value);
    }

    /**
     * Assert that no request matching the class name, URL pattern or closure was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertNotSent(string|callable $request): void
    {
        $this->mockClient()->assertNotSent($request);
    }

    /**
     * Assert that the fake recorded no requests at all.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertNothingSent(): void
    {
        $this->mockClient()->assertNothingSent();
    }

    /**
     * Assert how many requests were sent, optionally narrowed to one request class.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSentCount(int $count, ?string $requestClass = null): void
    {
        $this->mockClient()->assertSentCount($count, $requestClass);
    }

    /**
     * Assert that the requests were sent in the given order, and nothing else with them.
     *
     * @param  array<Closure|class-string<Request>|string>  $callbacks
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSentInOrder(array $callbacks): void
    {
        $this->mockClient()->assertSentInOrder($callbacks);
    }

    /**
     * Every response the fake recorded, in the order they came back.
     *
     * @return array<Response>
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function recorded(): array
    {
        return $this->mockClient()->getRecordedResponses();
    }

    /**
     * The last request the fake handled, or null when nothing was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function lastRequest(): ?Request
    {
        return $this->mockClient()->getLastRequest();
    }

    /**
     * The last response the fake returned, or null when nothing was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function lastResponse(): ?Response
    {
        return $this->mockClient()->getLastResponse();
    }

    /**
     * The mock attached by {@see self::fake()}, which the proxies above read from.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    private function mockClient(): MockClient
    {
        $mockClient = $this->connector()->getMockClient();

        if (! $mockClient instanceof MockClient) {
            throw new FakeNotInstalledException;
        }

        return $mockClient;
    }
}
