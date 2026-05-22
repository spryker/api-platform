<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\ApiPlatform\OpenApi\Normalizer;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IdentifiersExtractorInterface;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * API Platform has the IRI as the id property in the response. For Backwards Compatibility reasons we want to have
 * the entity identifier.
 */
class IdNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function __construct(
        private readonly IdentifiersExtractorInterface $identifiersExtractor,
    ) {
    }

    /**
     * @return \ArrayObject<array-key, mixed>|array<string, mixed>|string|float|int|bool|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // Recursion guard: prevents this normalizer from being called again
        // when delegating to the next normalizer in the chain
        $context[static::class] = true;

        // When gen_id is disabled, pre-set the IRI with the entity identifier
        // to prevent the JSON:API ItemNormalizer from calling the IRI converter
        if (($context['gen_id'] ?? true) === false) {
            $identifiers = $this->extractIdentifiersSafely($object);
            $identifier = array_pop($identifiers);

            if ($identifier === null) {
                $identifier = $this->extractFallbackIdentifier($object);
            }

            if ($identifier !== null) {
                $context['iri'] = (string)$identifier;
            }
        }

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data)) {
            return $data;
        }

        // Included resources (inside `included[]`) are normalized as a flat array with `type`/`id`
        // at the root (no `data` wrapper). Fill an empty `id` from the resource's identifier so
        // JSON:API output carries the right value when IriConverter cannot build it on its own
        // (e.g. write-only resources or sub-resources whose Get operation uses `read: false`).
        if (!isset($data['data']) && isset($data['type']) && ($data['id'] ?? '') === '') {
            $identifiers = $this->extractIdentifiersSafely($object);
            $identifier = array_pop($identifiers);

            if ($identifier === null) {
                $identifier = $this->extractFallbackIdentifier($object);
            }

            if ($identifier !== null && $identifier !== '') {
                $data['id'] = (string)$identifier;
            }

            return $data;
        }

        if (!isset($data['data'])) {
            return $data;
        }

        if (isset($data['data']['type']) && isset($data['data']['id'])) {
            $identifiers = $this->extractIdentifiersSafely($object);
            $identifier = array_pop($identifiers);

            // Singleton resources (e.g. catalog-search, checkout-data) use the type name
            // as a synthetic identifier for API Platform IRI generation.
            // The old Glue REST API returned null IDs for these resources.
            if ($identifier !== null && $identifier === $data['data']['type']) {
                $identifier = null;

                // Strip the synthetic identifier suffix from the self-link
                // (e.g. "/checkout-data/checkout-data" → "/checkout-data")
                $this->stripSyntheticIdentifierFromSelfLink($data['data'], $data['data']['type']);
            }

            $data['data']['id'] = $identifier;

            if ($identifier !== null && ($context['operation'] ?? null) instanceof GetCollection) {
                $this->ensureIdentifierInSelfLink($data['data'], (string)$identifier);
            }
        }

        // Fix empty `included[].id` for sub-resources that the inner JSON:API normalizer cannot
        // resolve via IriConverter (e.g. sub-resources whose Get operation uses `read: false`).
        // Conservative defaults: only run when an item has empty `id` AND carries an
        // identifier-shaped attribute (`uuid` or `id`). Resources whose `id` is already populated
        // or that don't expose such an attribute remain untouched.
        $data = $this->fillEmptyIncludedIdsFromAttributes($data);

        // Restore `id` in attributes: API Platform's ReservedAttributeNameConverter renames the
        // `id` attribute to `_id` to avoid JSON:API spec conflicts. Rename it back to preserve
        // backward compatibility with the legacy Glue REST API which returned `id` in attributes.
        if (isset($data['data']['attributes']['_id'])) {
            $data['data']['attributes']['id'] = $data['data']['attributes']['_id'];
            unset($data['data']['attributes']['_id']);
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
     * Fills empty `id` on `included[]` items from their `attributes.uuid` or `attributes.id`.
     *
     * Inner JSON:API ItemNormalizer relies on IriConverter to produce `included[].id`. When a
     * sub-resource is rendered via a `read: false` Get operation (no provider, no real fetch),
     * IriConverter cannot build the URI from the resource alone and leaves `id` empty.
     * This is a safety net: only applies when `id` is empty AND an identifier-shaped attribute
     * is available — so it cannot accidentally overwrite a correctly-resolved id elsewhere.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function fillEmptyIncludedIdsFromAttributes(array $data): array
    {
        if (!isset($data['included']) || !is_array($data['included'])) {
            return $data;
        }

        foreach ($data['included'] as $idx => $item) {
            if (!is_array($item) || ($item['id'] ?? '') !== '') {
                continue;
            }

            $fallback = (string)($item['attributes']['uuid'] ?? $item['attributes']['id'] ?? '');

            if ($fallback !== '') {
                $data['included'][$idx]['id'] = $fallback;
            }
        }

        return $data;
    }

    /**
     * Extracts identifiers from the object, returning an empty array if extraction
     * fails (e.g. for sub-resources where uriVariable identifiers like customerReference
     * don't exist on the resource itself).
     *
     * @return array<string, mixed>
     */
    protected function extractIdentifiersSafely(object $object): array
    {
        try {
            return $this->identifiersExtractor->getIdentifiersFromItem($object);
        } catch (RuntimeException) {
            return [];
        }
    }

    /**
     * Tries to read the identifier-marked property (typically `uuid` or `id`) directly
     * from the resource object as a fallback when IdentifiersExtractor fails.
     */
    protected function extractFallbackIdentifier(object $object): ?string
    {
        foreach (['uuid', 'id'] as $propertyName) {
            if (property_exists($object, $propertyName)) {
                $value = $object->{$propertyName};

                if ($value !== null) {
                    return (string)$value;
                }
            }
        }

        return null;
    }

    /**
     * Appends the identifier to `links.self` when the IRI converter produced a collection URL
     * (e.g. '/wishlists' instead of '/wishlists/{uuid}') for an item inside a GetCollection response.
     *
     * @param array<string, mixed> $resourceData
     */
    protected function ensureIdentifierInSelfLink(array &$resourceData, string $identifier): void
    {
        if (!isset($resourceData['links']['self']) || !is_string($resourceData['links']['self'])) {
            return;
        }

        $selfLink = $resourceData['links']['self'];

        $questionMarkPosition = strpos($selfLink, '?');

        if ($questionMarkPosition !== false) {
            $selfLink = substr($selfLink, 0, $questionMarkPosition);
        }

        if (str_ends_with($selfLink, '/' . $identifier)) {
            return;
        }

        // Self-link is the collection URL — append the identifier to produce the item URL.
        // Query string is intentionally dropped: item self-links must not inherit collection params.
        $resourceData['links']['self'] = rtrim($selfLink, '/') . '/' . $identifier;
    }

    /**
     * @param array<string, mixed> $resourceData
     */
    protected function stripSyntheticIdentifierFromSelfLink(array &$resourceData, string $syntheticIdentifier): void
    {
        if (!isset($resourceData['links']['self']) || !is_string($resourceData['links']['self'])) {
            return;
        }

        $suffix = '/' . $syntheticIdentifier;
        $selfLink = $resourceData['links']['self'];

        // Separate query string before checking the path suffix
        $queryString = '';
        $questionMarkPosition = strpos($selfLink, '?');

        if ($questionMarkPosition !== false) {
            $queryString = substr($selfLink, $questionMarkPosition);
            $selfLink = substr($selfLink, 0, $questionMarkPosition);
        }

        if (!str_ends_with($selfLink, $suffix)) {
            return;
        }

        $basePath = substr($selfLink, 0, -strlen($suffix));

        // When basePath has no path component beyond the host (e.g. `http://host` after
        // stripping `/<type>` from `http://host/<type>`), the original URL was already
        // canonical — there is no collection segment to pluralization-fix and the host
        // segment must NOT be touched. Leave the URL unchanged.
        $basePathComponent = parse_url($basePath, PHP_URL_PATH);

        if ($basePathComponent === null || $basePathComponent === '' || $basePathComponent === '/') {
            return;
        }

        // API Platform may pluralize the collection segment (e.g. "checkout-datas")
        // which differs from the actual resource type name ("checkout-data").
        // Detect and replace the pluralized segment so the self-link matches
        // the canonical endpoint path.
        $lastSlashPosition = strrpos($basePath, '/');

        if ($lastSlashPosition !== false) {
            $collectionSegment = substr($basePath, $lastSlashPosition + 1);

            if ($collectionSegment !== '' && $collectionSegment !== $syntheticIdentifier) {
                $basePath = substr($basePath, 0, $lastSlashPosition + 1) . $syntheticIdentifier;
            }
        }

        $resourceData['links']['self'] = $basePath . $queryString;
    }
}
