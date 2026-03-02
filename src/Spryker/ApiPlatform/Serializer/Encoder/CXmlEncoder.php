<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Serializer\Encoder;

use CXml\Model\CXml;
use CXml\Serializer;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\NormalizationAwareInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Throwable;

/**
 * Encodes and decodes data in cXML format using the FriendsOfCXML library.
 *
 * This encoder handles cXML serialization/deserialization for API Platform.
 * Since cXML and regular XML share the same mime type (application/xml, text/xml),
 * this encoder replaces the standard XmlEncoder, but internally delegates to it for non-cXML data.
 */
class CXmlEncoder implements EncoderInterface, DecoderInterface, NormalizationAwareInterface
{
    protected Serializer $cxmlSerializer;

    public function __construct(protected XmlEncoder $decoratedXmlEncoder)
    {
        $this->cxmlSerializer = Serializer::create();
    }

    /**
     * @param mixed $data
     * @param string $format
     * @param array<string, mixed> $context
     *
     * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
     *
     * @return string
     */
    public function encode(mixed $data, string $format, array $context = []): string
    {
        if (!$data instanceof CXml) {
            return $this->decoratedXmlEncoder->encode($data, $format, $context);
        }

        try {
            return $this->cxmlSerializer->serialize($data);
        } catch (Throwable $e) {
            throw new UnexpectedValueException(
                sprintf('An error occurred while encoding cXML data: %s', $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * @param string $data
     * @param string $format
     * @param array<string, mixed> $context
     *
     * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
     *
     * @return mixed
     */
    public function decode(string $data, string $format, array $context = []): mixed
    {
        // Check if data contains cXML root element
        if ($this->isCxml($data) === false) {
            // Not cXML format, delegate to standard XML encoder
            return $this->decoratedXmlEncoder->decode($data, $format, $context);
        }

        try {
            return $this->cxmlSerializer->deserialize($data);
        } catch (Throwable $e) {
            throw new UnexpectedValueException(
                sprintf('An error occurred while decoding cXML data: %s', $e->getMessage()),
                0,
                $e,
            );
        }
    }

    public function supportsEncoding(string $format): bool
    {
        return $this->decoratedXmlEncoder->supportsEncoding($format);
    }

    public function supportsDecoding(string $format): bool
    {
        return $this->decoratedXmlEncoder->supportsDecoding($format);
    }

    protected function isCxml(string $data): bool
    {
        return (bool)preg_match('/<cXML\b[^>]*>/i', $data);
    }
}
