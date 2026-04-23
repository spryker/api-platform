<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\ApiPlatform\OpenApi\Normalizer;

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

        if (!is_array($data) || !isset($data['data'])) {
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
     * @param array<string, mixed> $resourceData
     *
     * @return void
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
