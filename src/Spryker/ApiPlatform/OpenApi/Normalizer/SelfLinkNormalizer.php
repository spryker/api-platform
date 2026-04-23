<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\Normalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * We need to have a self link reference as we do not use the IRI but the entity id as id. For Backwards Compatibility this
 * normalizer adds the self link reference.
 */
class SelfLinkNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @return \ArrayObject<array-key, mixed>|array<string, mixed>|string|float|int|bool|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // Recursion guard: prevents this normalizer from being called again
        // when delegating to the next normalizer in the chain
        $context[static::class] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data) || !isset($data['data'])) {
            return $data;
        }

        if (($context['gen_id'] ?? true) === false) {
            $this->addRequestUriSelfLink($data, $context);
        } else {
            $this->addSelfLinksToData($data, $context);
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[static::class])) {
            return false;
        }

        if ($format !== 'jsonapi') {
            return false;
        }

        return is_object($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => false,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return void
     */
    protected function addSelfLinksToData(array &$data, array $context = []): void
    {
        if (!isset($data['data'])) {
            return;
        }

        if ($this->isSingleResource($data['data'])) {
            $this->addSelfLinkToResource($data['data'], $context);
        } elseif ($this->isCollection($data['data'])) {
            foreach ($data['data'] as &$resource) {
                $this->addSelfLinkToResource($resource, $context);
            }
        }

        if (isset($data['included']) && is_array($data['included'])) {
            foreach ($data['included'] as &$includedResource) {
                $this->addSelfLinkToIncludedResource($includedResource, $context);
            }
        }

        // Promote top-level collection links (self/first/last/prev/next) to absolute URLs.
        // API Platform's CollectionNormalizer uses ABS_PATH by default, producing relative paths.
        if (isset($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as $key => &$link) {
                if (is_string($link)) {
                    $link = $this->toAbsoluteUrl($link, $context);
                }
            }
        }
    }

    protected function isSingleResource(mixed $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        return isset($data['type']) && isset($data['id']);
    }

    /**
     * Returns true when the IRI ends with /{type}, indicating a singleton resource whose IRI
     * uses the type name as a synthetic identifier (e.g. /checkouts/checkout for type "checkout").
     * IdNormalizer (outer wrapper) will later strip this synthetic suffix and fix pluralization.
     */
    protected function isSingletonIri(string $iri, string $type): bool
    {
        return str_ends_with($iri, '/' . $type);
    }

    protected function isCollection(mixed $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        if (!$data) {
            return false;
        }

        $firstElement = reset($data);

        return is_array($firstElement) && isset($firstElement['type']) && isset($firstElement['id']);
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, mixed> $context
     *
     * @return void
     */
    protected function addSelfLinkToResource(array &$resource, array $context = []): void
    {
        if (!isset($resource['type']) || !isset($resource['id'])) {
            return;
        }

        if (!isset($resource['links'])) {
            $resource['links'] = [];
        }

        // When API Platform generates the IRI for a sub-resource operation (e.g. PATCH /carts/{id}/items/{key})
        // the returned parent resource may get the nested operation URI as its IRI instead of its own canonical URI.
        // Extract only the canonical segment /{type}/{identifier} from the IRI to produce the correct self-link.
        $link = $this->extractCanonicalSelfLink((string)$resource['id'], $resource['type']);

        // API Platform's JSON:API normalizer generates relative IRIs (ABS_PATH). Promote to absolute URL
        // using the scheme and host from the current request URI so self-links are fully qualified.
        $link = $this->toAbsoluteUrl($link, $context);

        // Preserve query string (e.g. ?include=items) from the original request for backward compatibility,
        // but only when the request was directly for this resource (not a sub-resource operation).
        // For sub-resource operations (e.g. POST /carts/{id}/items), the parent resource self link
        // must be canonical without the sub-resource query string.
        $queryString = $this->extractQueryStringFromContext($context);

        if ($this->isDirectResourceRequest($link, $context)) {
            if ($queryString !== null) {
                $link .= '?' . $queryString;
            }
        } elseif ($this->isSingletonIri($link, $resource['type'])) {
            // Singleton resource: IRI ends with /{type} (e.g. /checkouts/checkout).
            // IdNormalizer (outer wrapper) will strip the synthetic identifier and fix pluralization,
            // preserving the query string we attach here.
            if ($queryString !== null) {
                $link .= '?' . $queryString;
            }
        }

        $resource['links']['self'] = $link;
    }

    /**
     * Extracts the query string from the normalization context.
     * API Platform's SerializerContextBuilder sets 'uri' to the full request URI including query string.
     *
     * @param array<string, mixed> $context
     */
    protected function extractQueryStringFromContext(array $context): ?string
    {
        $uri = $context['uri'] ?? null;

        if ($uri === null) {
            return null;
        }

        $queryString = parse_url((string)$uri, PHP_URL_QUERY);

        return ($queryString !== null && $queryString !== false) ? $queryString : null;
    }

    /**
     * Returns true when the request URI path matches the canonical self link path exactly,
     * meaning the request targeted this resource directly (not a sub-resource operation).
     * For sub-resource operations (e.g. POST /carts/{id}/items), the self link path is a
     * prefix of the request path — in that case query params must not be propagated to the parent.
     *
     * @param array<string, mixed> $context
     */
    protected function isDirectResourceRequest(string $selfLink, array $context): bool
    {
        $uri = $context['uri'] ?? null;

        if ($uri === null) {
            return false;
        }

        $selfLinkPath = parse_url($selfLink, PHP_URL_PATH);
        $requestPath = parse_url((string)$uri, PHP_URL_PATH);

        return $selfLinkPath !== false && $requestPath !== false && $selfLinkPath === $requestPath;
    }

    /**
     * Extracts the canonical self-link by finding the /{type}/{identifier} segment in the IRI.
     * Handles cases where API Platform incorrectly generates a nested operation URI as the resource IRI.
     *
     * Example: "http://host/guest-carts/uuid/guest-cart-items/key" with type "guest-carts"
     *          → "http://host/guest-carts/uuid"
     */
    protected function extractCanonicalSelfLink(string $iri, string $type): string
    {
        $pattern = '~(.*/' . preg_quote($type, '~') . '/[^/]+)~';

        if (preg_match($pattern, $iri, $matches)) {
            return $matches[1];
        }

        return $iri;
    }

    /**
     * Adds a self link using the request URI when IRI-based self links are disabled (gen_id: false).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return void
     */
    protected function addRequestUriSelfLink(array &$data, array $context): void
    {
        if (!isset($data['data']) || !is_array($data['data']) || !isset($data['data']['type'])) {
            return;
        }

        // API Platform's SerializerContextBuilder always sets 'uri' to the full request URI.
        $uri = $context['uri'] ?? null;

        if ($uri === null) {
            return;
        }

        if (!isset($data['data']['links'])) {
            $data['data']['links'] = [];
        }

        $data['data']['links']['self'] = $uri;
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, mixed> $context
     *
     * @return void
     */
    protected function addSelfLinkToIncludedResource(array &$resource, array $context = []): void
    {
        if (!isset($resource['type']) || !isset($resource['id'])) {
            return;
        }

        if (!isset($resource['links'])) {
            $resource['links'] = [];
        }

        $resource['links']['self'] = $this->toAbsoluteUrl((string)$resource['id'], $context);
    }

    /**
     * Promotes a relative path to an absolute URL using the scheme and host from the request URI in context.
     * Returns the input unchanged when it is already absolute or when the request URI is unavailable.
     *
     * @param array<string, mixed> $context
     */
    protected function toAbsoluteUrl(string $path, array $context): string
    {
        if (!str_starts_with($path, '/')) {
            return $path;
        }

        $requestUri = $context['uri'] ?? null;

        if ($requestUri === null) {
            return $path;
        }

        $scheme = parse_url((string)$requestUri, PHP_URL_SCHEME);
        $host = parse_url((string)$requestUri, PHP_URL_HOST);

        if (!$scheme || !$host) {
            return $path;
        }

        $port = parse_url((string)$requestUri, PHP_URL_PORT);
        $base = $scheme . '://' . $host . ($port !== null ? ':' . $port : '');

        return $base . $path;
    }
}
