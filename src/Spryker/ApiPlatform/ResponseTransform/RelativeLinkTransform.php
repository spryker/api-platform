<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\ResponseTransform;

use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Promotes relative link URLs (LINK_NAMES under `links` keys, any nesting level) to absolute URLs.
 * API Platform's CollectionNormalizer generates ABS_PATH links (/path) by default, and the old Glue
 * REST API always returned fully-qualified URLs (http://host/path).
 */
class RelativeLinkTransform
{
    /**
     * @var array<string>
     *
     * The `links` names this stack produces. Used by BOTH hasRelativeLink() and
     * promoteRelativeLinks() — extend this set to promote additional link names.
     */
    protected const array LINK_NAMES = ['self', 'related', 'first', 'last', 'prev', 'next'];

    public function transform(Response $response, Request $request): void
    {
        if (!$request->attributes->has(RequestAttribute::API_RESOURCE_CLASS)) {
            return;
        }

        $contentType = $response->headers->get('Content-Type') ?? '';

        if (!str_contains($contentType, 'json')) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        // With url_generation_strategy=ABS_URL links are normally absolute already, so the
        // whole-body decode/encode below is skipped on the happy path.
        if (!$this->hasRelativeLink($content)) {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return;
        }

        $baseUrl = $request->getScheme() . '://' . $request->getHttpHost();

        $this->promoteRelativeLinks($data, $baseUrl);

        $response->setContent((string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Detects a relative link in the raw response body without decoding it.
     * The escaped variant (`"self":"\/`) cannot occur while the encoder emits unescaped slashes,
     * but it keeps link promotion working against a body encoded with default flags.
     */
    protected function hasRelativeLink(string $content): bool
    {
        foreach (static::LINK_NAMES as $linkName) {
            if (
                str_contains($content, sprintf('"%s":"/', $linkName))
                || str_contains($content, sprintf('"%s":"\/', $linkName))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Promotes the same LINK_NAMES set that hasRelativeLink() guards on, so the guard can never
     * be narrower than the promotion.
     *
     * @param array<string, mixed> $data
     */
    protected function promoteRelativeLinks(array &$data, string $baseUrl): void
    {
        if (isset($data['links']) && is_array($data['links'])) {
            foreach (static::LINK_NAMES as $linkName) {
                $link = $data['links'][$linkName] ?? null;

                if (is_string($link) && str_starts_with($link, '/')) {
                    $data['links'][$linkName] = $baseUrl . $link;
                }
            }
        }

        // Recurse into data items and included resources
        foreach (['data', 'included'] as $key) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                continue;
            }

            if (isset($data[$key]['links'])) {
                // Single resource
                $this->promoteRelativeLinks($data[$key], $baseUrl);
            } else {
                // Collection of items
                foreach ($data[$key] as &$item) {
                    if (is_array($item)) {
                        $this->promoteRelativeLinks($item, $baseUrl);
                    }
                }
                unset($item);
            }
        }
    }
}
