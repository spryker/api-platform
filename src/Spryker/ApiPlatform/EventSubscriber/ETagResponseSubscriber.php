<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds ETag response headers when providers store an ETag value in request attributes.
 */
class ETagResponseSubscriber implements EventSubscriberInterface
{
    protected const string REQUEST_ATTRIBUTE_ETAG = '_etag';

    protected const string HEADER_ETAG = 'ETag';

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $etag = $request->attributes->get(static::REQUEST_ATTRIBUTE_ETAG);

        if ($etag === null) {
            return;
        }

        $etag = preg_replace('/[\r\n"\\\\]/', '', (string)$etag);

        $response = $event->getResponse();
        $response->headers->set(static::HEADER_ETAG, sprintf('"%s"', $etag));
    }
}
