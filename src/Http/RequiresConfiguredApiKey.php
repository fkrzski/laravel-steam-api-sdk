<?php

declare(strict_types=1);

namespace Fkrzski\LaravelSteamApiSdk\Http;

use Fkrzski\LaravelSteamApiSdk\Exceptions\SteamApiKeyMissingException;
use Fkrzski\LaravelSteamApiSdk\SteamServiceProvider;
use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Saloon\Contracts\RequestMiddleware;
use Saloon\Http\PendingRequest;

/**
 * Refuses a request that needs the Steam Web API key while none is configured.
 *
 * Registered on the connector by {@see SteamServiceProvider}, which builds it
 * with a blank key rather than refusing to build at all: Steam serves the
 * endpoints marked {@see SendsNoApiKey} anonymously, and the connector strips
 * the key from their query, so an application calling only those needs no key.
 * Everything else fails here — before a request goes out, with the message
 * naming the config value to set rather than whatever Steam answers a blank key
 * with.
 */
final readonly class RequiresConfiguredApiKey implements RequestMiddleware
{
    public function __construct(private bool $keyConfigured) {}

    /**
     * @throws SteamApiKeyMissingException when the request needs a key and none is set
     */
    public function __invoke(PendingRequest $pendingRequest): void
    {
        if ($this->keyConfigured || $pendingRequest->getRequest() instanceof SendsNoApiKey) {
            return;
        }

        throw new SteamApiKeyMissingException;
    }
}
