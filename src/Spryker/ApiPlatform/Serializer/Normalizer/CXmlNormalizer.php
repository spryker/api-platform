<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Serializer\Normalizer;

use ApiPlatform\Serializer\ItemNormalizer;
use CXml\Model\CXml;
use CXml\Serializer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes and denormalizes CXml objects for API Platform.
 *
 * This normalizer handles the transformation between CXml objects and arrays.
 * It works in conjunction with the CXmlEncoder to provide full serialization support.
 */
class CXmlNormalizer implements NormalizerInterface, DenormalizerInterface
{
    protected const string FORMAT = 'xml';

    protected Serializer $cxmlSerializer;

    public function __construct(protected ItemNormalizer $decoratedItemNormalizer)
    {
        $this->cxmlSerializer = Serializer::create();
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
     * @param mixed $data
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @return bool
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $format === static::FORMAT;
    }

    /**
     * Checks whether the given class is supported for denormalization by this normalizer.
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
        return $format === static::FORMAT;
    }

    /**
     * Gets the types supported by this normalizer.
     *
     * @param string|null $format
     *
     * @return array<class-string|'object'|'*', bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        if ($format !== static::FORMAT) {
            return [];
        }

        return [
            'object' => false,
        ];
    }
}
