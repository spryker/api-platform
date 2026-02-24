<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\ApiPlatform\OpenApi\Normalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * When relations are empty they still exists in the response. We don't want that and this normalizer checks and removes them accordingly.
 */
class EmptyRelationshipNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @return \ArrayObject<array-key, mixed>|array<string, mixed>|string|float|int|bool|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[static::class] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data) || !isset($data['data']) || !isset($data['data']['relationships'])) {
            return $data;
        }

        // Filter out empty relationships
        $relationships = array_filter($data['data']['relationships'], function ($relationshipData) {
            return isset($relationshipData['data']) && count($relationshipData['data']);
        });

        // Cleanup existing relationships to replace (if exists) with cleaned up ones
        unset($data['data']['relationships']);

        if (count($relationships)) {
            $data['data']['relationships'] = $relationships;
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
