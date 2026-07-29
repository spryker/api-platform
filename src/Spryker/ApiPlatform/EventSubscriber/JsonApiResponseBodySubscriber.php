<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Spryker\ApiPlatform\ResponseTransform\JsonApiRelationshipNormalizerTransform;
use Spryker\ApiPlatform\ResponseTransform\JsonApiResolvedRelationshipTransform;
use Spryker\ApiPlatform\ResponseTransform\PaginationLinksTransform;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Orchestrates the JSON:API response-body transforms in a single decode/encode pass.
 *
 * Previously each transform was its own KernelEvents::RESPONSE subscriber and each independently
 * did json_decode(getContent()) -> mutate -> setContent(json_encode()). For a response with
 * includes that meant the full body was decoded and re-encoded three times per request — pure,
 * data-size-proportional CPU waste (the intermediate encode/decode round-trips are lossless, so
 * chaining them produced the same array the next subscriber would have decoded).
 *
 * This subscriber runs at the former first transform's priority (-257, i.e. after
 * JsonApiContentTypeCleanupSubscriber at -256, which operates on the raw string and is left
 * untouched), decodes the body once, invokes each transform's applyTo() in the exact original
 * priority order (-257 -> -258 -> -259), and encodes once, only if some transform reported a
 * change. When nothing changes, the original API Platform-serialized body is left byte-for-byte
 * intact — identical to the previous behavior where no subscriber called setContent().
 */
class JsonApiResponseBodySubscriber implements EventSubscriberInterface
{
    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    public function __construct(
        protected JsonApiRelationshipNormalizerTransform $relationshipNormalizer,
        protected JsonApiResolvedRelationshipTransform $resolvedRelationship,
        protected PaginationLinksTransform $paginationLinks,
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -257],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type') ?? '';

        if (!str_starts_with($contentType, static::CONTENT_TYPE_JSON_API)) {
            return;
        }

        $content = $response->getContent();

        if ($content === false) {
            return;
        }

        $request = $event->getRequest();

        // Fast path: without one of these markers no transform can modify the body, so the decode
        // is skipped. "currentPage" covers pagination link building; "pagination":null covers
        // stripNullPaginationAttribute() (compact JSON, structural quotes are never escaped).
        if (
            !str_contains($content, '"relationships"')
            && !str_contains($content, '"included"')
            && !str_contains($content, '"currentPage"')
            && !str_contains($content, '"pagination":null')
            && !$request->attributes->get(JsonApiResolvedRelationshipTransform::REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS)
        ) {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return;
        }

        // Order matters and mirrors the former subscriber priorities (-257, -258, -259).
        // Every transform must run, so the applyTo() call is always the left operand of `||`.
        $modified = $this->relationshipNormalizer->applyTo($data);
        $modified = $this->resolvedRelationship->applyTo($data, $request) || $modified;
        $modified = $this->paginationLinks->applyTo($data, $request) || $modified;

        if ($modified) {
            $response->setContent((string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }
}
