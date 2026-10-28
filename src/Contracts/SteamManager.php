<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Contracts;

use Closure;
use DateTimeInterface;
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
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Pool;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * What the `Steam` facade resolves.
 *
 * Bind this interface to swap the manager — the shipped class is `final`, so the
 * container is the seam.
 */
interface SteamManager
{
    /**
     * The underlying Saloon connector. Use this as an escape hatch for
     * advanced features (e.g. building custom requests) not exposed here.
     */
    public function connector(): SteamConnector;

    /**
     * Send a Steam request and return the raw Saloon response.
     */
    public function send(Request $request): Response;

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
    ): Pool;

    /**
     * The IPlayerService endpoints, reached fluently.
     */
    public function players(): PlayersResource;

    /**
     * The ISteamUser endpoints, reached fluently.
     */
    public function users(): UsersResource;

    /**
     * The ISteamUserStats endpoints, reached fluently.
     */
    public function stats(): StatsResource;

    /**
     * The ISteamApps endpoints, reached fluently.
     */
    public function apps(): AppsResource;

    /**
     * The ISteamNews endpoint, reached fluently.
     */
    public function news(): NewsResource;

    /**
     * The ISteamWebAPIUtil endpoints, reached fluently.
     */
    public function webApi(): WebApiResource;

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
    ): LazyCollection;

    /**
     * Swap the connector's HTTP client for a Saloon mock, returning it for assertions.
     *
     * A faked retry keeps its tries but not the pause between them.
     *
     * @param  array<array-key, (callable(PendingRequest): mixed)|Fixture|MockResponse>  $responses
     *
     * @throws FakeOutsideTestsException when the application is not running tests
     */
    public function fake(array $responses = []): SteamFake;

    /**
     * Assert that a request matching the class name, URL pattern or closure was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSent(string|callable $value): void;

    /**
     * Assert that no request matching the class name, URL pattern or closure was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertNotSent(string|callable $request): void;

    /**
     * Assert that the fake recorded no requests at all.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertNothingSent(): void;

    /**
     * Assert how many requests were sent, optionally narrowed to one request class.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSentCount(int $count, ?string $requestClass = null): void;

    /**
     * Assert that the requests were sent in the given order, and nothing else with them.
     *
     * @param  array<Closure|class-string<Request>|string>  $callbacks
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function assertSentInOrder(array $callbacks): void;

    /**
     * Every response the fake recorded, in the order they came back.
     *
     * @return array<Response>
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function recorded(): array;

    /**
     * The last request the fake handled, or null when nothing was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function lastRequest(): ?Request;

    /**
     * The last response the fake returned, or null when nothing was sent.
     *
     * @throws FakeNotInstalledException when no fake is attached
     */
    public function lastResponse(): ?Response;
}
