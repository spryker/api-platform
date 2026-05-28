<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Restores the legacy Glue behavior of accepting requests that omit the
 * `Accept` header, by setting `Accept: application/vnd.api+json` before
 * API Platform's content negotiation runs.
 *
 * API Platform's AddFormatListener (priority 28) would otherwise either reject
 * the request (when the client sends an Accept the negotiator cannot satisfy)
 * or fall through to a default that does not match the legacy Glue contract.
 */
class AcceptHeaderFallbackSubscriber implements EventSubscriberInterface
{
    protected const string ACCEPT_HEADER = 'Accept';

    protected const string JSON_API_MIME_TYPE = 'application/vnd.api+json';

    /**
     * Runs after Symfony's RouterListener (32) but before
     * AddFormatListener (28).
     */
    protected const int PRIORITY = 30;

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', static::PRIORITY],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $accept = $request->headers->get(static::ACCEPT_HEADER);

        if ($accept === null || $accept === '') {
            $request->headers->set(static::ACCEPT_HEADER, static::JSON_API_MIME_TYPE);

            return;
        }

        if (str_contains($accept, static::JSON_API_MIME_TYPE)) {
            return;
        }

        // Browser-style accept headers that volunteer a weak wildcard (e.g.
        // `text/html,…,*/*;q=0.8`) do not map cleanly to vnd.api+json across
        // negotiator versions. Swap only the literal `*/*` token for vnd.api+json
        // and leave the concrete types in place: this restores legacy Glue's
        // no-header behaviour (the negotiator falls through the unsatisfiable
        // higher-q types to the json entry) without removing the 406 path for
        // concrete unsupported types like `application/xml`. The early return
        // above guarantees vnd.api+json is absent here, so the token can never
        // be added twice.
        if (str_contains($accept, '*/*')) {
            $request->headers->set(static::ACCEPT_HEADER, str_replace('*/*', static::JSON_API_MIME_TYPE, $accept));
        }
    }
}
