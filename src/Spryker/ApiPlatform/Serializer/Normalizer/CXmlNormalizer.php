<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Serializer\Normalizer;

use ApiPlatform\Serializer\ItemNormalizer;
use CXml\Model\CXml;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Decorates API Platform's generic ItemNormalizer, taking its place in the normalizer chain.
 *
 * For every format the decorated normalizer handles, calls are passed through unchanged; on top of
 * that, already decoded `CXml` objects are passed through denormalization as-is (they are produced
 * by the CXmlEncoder and need no further transformation). Works in conjunction with the CXmlEncoder
 * to provide full cXML serialization support.
 */
class CXmlNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    public function __construct(protected ItemNormalizer $decoratedItemNormalizer)
    {
    }

    /**
     * The decorated normalizer needs the serializer to normalize nested values and relations, but only this
     * decorator is registered in the normalizer chain, so the serializer has to be forwarded manually.
     */
    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->decoratedItemNormalizer->setSerializer($serializer);
    }

    /**
     * @param mixed $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        return $this->decoratedItemNormalizer->normalize($object, $format, $context);
    }

    /**
     * Denormalizes array data into a CXml object.
     *
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if ($data instanceof CXml) {
            return $data;
        }

        return $this->decoratedItemNormalizer->denormalize($data, $type, $format, $context);
    }

    /**
     * Checks whether the given class is supported for normalization by this normalizer.
     *
     * Because this decorator takes the place of the decorated normalizer in the normalizer chain, it MUST
     * support everything the decorated one supports. Narrowing support down to cXML would leave every other
     * format that relies on the decorated normalizer (for example `csv`) to Symfony's plain object normalizer,
     * which ignores API Platform property metadata and therefore exposes non-readable properties.
     *
     * @param mixed $data
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @return bool
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decoratedItemNormalizer->supportsNormalization($data, $format, $context);
    }

    /**
     * Checks whether the given class is supported for denormalization by this normalizer.
     *
     * Already decoded cXML data is passed through as is, everything else follows the decorated normalizer.
     *
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @return bool
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if ($data instanceof CXml) {
            return true;
        }

        return $this->decoratedItemNormalizer->supportsDenormalization($data, $type, $format, $context);
    }

    /**
     * Gets the types supported by this normalizer.
     *
     * The decorated normalizer reports its object support as non-cacheable, which keeps the `supports*()` methods
     * above in play — including the cXML pass-through.
     *
     * @param string|null $format
     *
     * @return array<class-string|'object'|'*', bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return $this->decoratedItemNormalizer->getSupportedTypes($format);
    }
}
