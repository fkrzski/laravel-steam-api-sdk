<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when a facade assertion runs without a fake attached to the connector.
 *
 * The assertions read the mock's recorded responses, so without one there is
 * nothing to assert against and a silent pass would be worse than a failure.
 */
final class FakeNotInstalledException extends SteamApiException
{
    public function __construct()
    {
        parent::__construct(
            'No Steam fake is attached to the connector, so there is nothing to assert against. '
            .'Call Steam::fake() before the code under test sends its requests.'
        );
    }
}
