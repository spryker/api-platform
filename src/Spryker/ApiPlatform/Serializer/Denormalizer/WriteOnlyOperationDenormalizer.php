<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Serializer\Denormalizer;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Decorates the JSON:API item denormalizer to make write-only operations (`read: false`)
 * compatible with JSON:API request bodies that include `data.id` as required by the
 * JSON:API specification.
 *
 * Without this decorator, the JSON:API denormalizer treats body's `data.id` as the IRI
 * of an existing resource and tries to load it via IriConverter. For write-only
 * operations there is no canonical Get operation that can resolve such an IRI, so the
 * lookup fails with `400 "No route matches \"...\""`.
 *
 * The decorator pre-populates `OBJECT_TO_POPULATE` with a fresh resource instance,
 * which short-circuits the IRI-lookup branch in the denormalization routine
 * (the `if (!isset($context[OBJECT_TO_POPULATE]) && isset($data['data']['id']))` block).
 * That routine lives in {@see \ApiPlatform\JsonApi\Serializer\ItemNormalizer} up to
 * api-platform 4.3 and in {@see \ApiPlatform\JsonApi\Serializer\ItemDenormalizer} from
 * 4.4 on, so both services are decorated with this class.
 *
 * Effective scope: only operations whose `canRead() === false` whose request body has
 * `data.id` and where no provider has already populated `OBJECT_TO_POPULATE` via the
 * `data` request attribute. Standard read-eligible operations are unaffected.
 *
 * Implements {@see NormalizerInterface}, {@see DenormalizerInterface} and
 * {@see SerializerAwareInterface} because the decorated service handles all three —
 * the AbstractItemNormalizer parent class needs the Symfony Serializer set on it for
 * nested normalization, and consumers like `api_platform.jsonapi.normalizer.error`
 * type-hint `NormalizerInterface` on the decorated service.
 */
class WriteOnlyOperationDenormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    public function __construct(
        protected NormalizerInterface&DenormalizerInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if ($this->shouldPrePopulate($data, $type, $context)) {
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = new $type();
        }

        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // @phpstan-ignore arguments.count (symfony/serializer 6.4 keeps $context undeclared on the interface; 7.4 declares it)
        return $this->decorated->supportsDenormalization($data, $type, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return \ArrayObject<array-key, mixed>|array<array-key, mixed>|string|float|int|bool|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $this->decorated->normalize($object, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // @phpstan-ignore arguments.count (symfony/serializer 6.4 keeps $context undeclared on the interface; 7.4 declares it)
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function shouldPrePopulate(mixed $data, string $type, array $context): bool
    {
        if (isset($context[AbstractNormalizer::OBJECT_TO_POPULATE])) {
            return false;
        }

        if (!is_array($data) || !isset($data['data']['id'])) {
            return false;
        }

        $operation = $context['operation'] ?? null;

        if (!$operation instanceof HttpOperation) {
            return false;
        }

        if ($operation->canRead() !== false) {
            return false;
        }

        return class_exists($type);
    }
}
