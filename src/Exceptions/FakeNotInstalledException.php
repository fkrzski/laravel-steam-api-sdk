<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when a facade assertion or traffic reader runs without a fake attached.
 *
 * Both read the mock's recorded responses, so without one there is no history at
 * all — and reporting that beats a silent pass or an empty result.
 */
final class FakeNotInstalledException extends SteamApiException
{
    public function __construct()
    {
        parent::__construct(
            'No Steam fake is attached to the connector, so there is no recorded traffic to read. '
            .'Call Steam::fake() before the code under test sends its requests.'
        );
    }
}
