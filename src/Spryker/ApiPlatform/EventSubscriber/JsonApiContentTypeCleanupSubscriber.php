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
 * Applies backward-compatibility response fixups for JSON:API responses:
 *
 * 1. Strips the charset parameter from Content-Type header.
 *    Symfony automatically appends "; charset=utf-8" to text-based content types,
 *    but the legacy Glue REST API returned "application/vnd.api+json" without charset.
 *
 * 2. Restores the original query string order in self-link URLs.
 *    Symfony's Request::getQueryString() alphabetizes parameters, but the legacy
 *    Glue REST API preserved the original order from the client request.
 */
class JsonApiContentTypeCleanupSubscriber implements EventSubscriberInterface
{
    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type') ?? '';

        if (!str_starts_with($contentType, static::CONTENT_TYPE_JSON_API)) {
            return;
        }

        $response->headers->set('Content-Type', static::CONTENT_TYPE_JSON_API);

        $this->restoreOriginalQueryStringInSelfLinks($event);
    }

    protected function restoreOriginalQueryStringInSelfLinks(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $normalizedQueryString = $request->getQueryString();

        if (!$normalizedQueryString) {
            return;
        }

        // Build the query string from parsed parameters which preserves the original parameter order
        // (Symfony's getQueryString() alphabetizes parameters, but query->all() keeps the parse order)
        $originalOrderQueryString = urldecode(http_build_query($request->query->all(), '', '&'));

        if ($normalizedQueryString === $originalOrderQueryString) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();

        if ($content === false) {
            return;
        }

        // Replace both plain and JSON-HEX-AMP encoded forms of the query string,
        // since the serializer may use either '&' or '\u0026' depending on JSON encoding flags.
        $response->setContent(
            str_replace(
                [$normalizedQueryString, $this->jsonEncodeQueryString($normalizedQueryString)],
                [$originalOrderQueryString, $this->jsonEncodeQueryString($originalOrderQueryString)],
                $content,
            ),
        );
    }

    protected function jsonEncodeQueryString(string $queryString): string
    {
        return trim(
            (string)json_encode($queryString, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
            '"',
        );
    }
}
