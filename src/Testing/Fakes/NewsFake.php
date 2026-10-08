<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Testing\Fakes;

use Fkrzski\LaravelSteamApiSdk\Testing\Factories\AppNewsFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\Factories\NewsItemFactory;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamFake;
use Fkrzski\LaravelSteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;

/**
 * The ISteamNews endpoint, faked the way `Steam::news()` reaches it.
 */
final readonly class NewsFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function appNews(AppNewsFactory $news): SteamFake
    {
        $this->fake->addResponse(SteamResponse::appNews($news), GetNewsForAppRequest::class);

        return $this->fake;
    }

    /**
     * Pages the whole feed per request the way Steam does — see {@see SteamResponse::newsFeed()}.
     */
    public function newsFeed(NewsItemFactory ...$items): SteamFake
    {
        $this->fake->addResponse(SteamResponse::newsFeed(...$items), GetNewsForAppRequest::class);

        return $this->fake;
    }

    public function appNewsUnavailable(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::appNewsUnavailable(), GetNewsForAppRequest::class);

        return $this->fake;
    }
}
