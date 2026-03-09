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

        // Skip self link generation when IRI generation is disabled
        // because the id is an entity identifier, not a valid URL
        if (($context['gen_id'] ?? true) === false) {
            return $data;
        }

        $this->addSelfLinksToData($data);

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
     *
     * @return void
     */
    protected function addSelfLinksToData(array &$data): void
    {
        if (!isset($data['data'])) {
            return;
        }

        if ($this->isSingleResource($data['data'])) {
            $this->addSelfLinkToResource($data['data']);

            return;
        }

        if ($this->isCollection($data['data'])) {
            foreach ($data['data'] as &$resource) {
                $this->addSelfLinkToResource($resource);
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
     *
     * @return void
     */
    protected function addSelfLinkToResource(array &$resource): void
    {
        if (!isset($resource['type']) || !isset($resource['id'])) {
            return;
        }

        if (!isset($resource['links'])) {
            $resource['links'] = [];
        }

        $resource['links']['self'] = $resource['id'];
    }
}
