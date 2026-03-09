<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\ApiPlatform\OpenApi\Normalizer;

use ApiPlatform\Metadata\IdentifiersExtractorInterface;
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
            $identifiers = $this->identifiersExtractor->getIdentifiersFromItem($object);
            $identifier = array_pop($identifiers);

            if ($identifier !== null) {
                $context['iri'] = (string)$identifier;
            }
        }

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data) || !isset($data['data'])) {
            return $data;
        }

        if (isset($data['data']['type']) && isset($data['data']['id'])) {
            $identifiers = $this->identifiersExtractor->getIdentifiersFromItem($object);

            $data['data']['id'] = array_pop($identifiers);
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
}
