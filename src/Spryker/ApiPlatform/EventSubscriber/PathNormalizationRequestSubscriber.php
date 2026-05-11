<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Collapses consecutive slashes in the request path to a single slash so that
 * `POST //carts` routes identically to `POST /carts`.
 *
 * The legacy Glue REST stack tolerated multi-slash paths produced by the
 * `GlueRest::execute()` test helper, which builds URLs as
 * `domain . '/' . $url` and yields `//carts` when the caller passes
 * `'/carts'`. API Platform routes via Symfony's UrlMatcher, which treats
 * `//carts` and `/carts` as distinct paths and returns 404 for the former,
 * breaking every Pyz/Robot test that relies on the helper's leading-slash
 * convention.
 */
class PathNormalizationRequestSubscriber implements EventSubscriberInterface
{
    /**
     * Higher than Symfony's RouterListener (32) and JsonApiRequestValidatorSubscriber (33).
     */
    protected const int PRIORITY = 1024;

    /**
     * @var list<string>
     */
    protected const array CACHED_REQUEST_PROPERTIES = ['pathInfo', 'requestUri', 'baseUrl', 'basePath'];

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
        $requestUri = (string)$request->server->get('REQUEST_URI', '');

        if ($requestUri === '' || !str_starts_with($requestUri, '//')) {
            return;
        }

        $normalizedUri = $this->normalizeRequestUri($requestUri);

        if ($normalizedUri === $requestUri) {
            return;
        }

        $request->server->set('REQUEST_URI', $normalizedUri);
        $this->clearCachedRequestPaths($request);
    }

    protected function normalizeRequestUri(string $requestUri): string
    {
        $queryPosition = strpos($requestUri, '?');
        $path = $queryPosition === false ? $requestUri : substr($requestUri, 0, $queryPosition);
        $query = $queryPosition === false ? '' : substr($requestUri, $queryPosition);

        $normalizedPath = (string)preg_replace('#^/+#', '/', $path);

        return $normalizedPath . $query;
    }

    protected function clearCachedRequestPaths(Request $request): void
    {
        foreach (static::CACHED_REQUEST_PROPERTIES as $property) {
            (new ReflectionProperty(Request::class, $property))->setValue($request, null);
        }
    }
}
