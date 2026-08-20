<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->tia()->locally();

function steamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}
