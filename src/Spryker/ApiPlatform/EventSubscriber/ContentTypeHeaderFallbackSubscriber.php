<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Restores the legacy Glue behavior of accepting write requests that omit the
 * `Content-Type` header, by setting `Content-Type: application/vnd.api+json`
 * before API Platform's content negotiation runs.
 *
 * Without this, API Platform's DeserializeProvider rejects a body-bearing request
 * that has no Content-Type with `415 Unsupported Media Type` ("The \"Content-Type\"
 * header must exist."), and content negotiation never resolves an input format.
 * This mirrors {@see AcceptHeaderFallbackSubscriber}, which does the same for the
 * `Accept` header.
 */
class ContentTypeHeaderFallbackSubscriber implements EventSubscriberInterface
{
    protected const string CONTENT_TYPE_HEADER = 'Content-Type';

    protected const string JSON_API_MIME_TYPE = 'application/vnd.api+json';

    /**
     * Methods that carry a request body and therefore require a Content-Type.
     *
     * @var list<string>
     */
    protected const array BODY_METHODS = [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH];

    /**
     * Runs after Symfony's RouterListener (32) but before AddFormatListener (28),
     * matching {@see AcceptHeaderFallbackSubscriber}.
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

        if (!in_array($request->getMethod(), static::BODY_METHODS, true)) {
            return;
        }

        $contentType = $request->headers->get(static::CONTENT_TYPE_HEADER);

        if ($contentType === null || $contentType === '') {
            $request->headers->set(static::CONTENT_TYPE_HEADER, static::JSON_API_MIME_TYPE);
        }
    }
}
