<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

/**
 * Thrown when the Steam fake is installed outside the testing environment.
 *
 * Nothing swaps the mock back out, so a stray fake would answer real traffic
 * for the life of the worker.
 */
final class FakeOutsideTestsException extends SteamApiException
{
    public function __construct()
    {
        parent::__construct(
            'Steam::fake() is only available while running tests. The application environment is '
            .'not "testing", so the fake was refused rather than left attached to the connector.'
        );
    }
}
