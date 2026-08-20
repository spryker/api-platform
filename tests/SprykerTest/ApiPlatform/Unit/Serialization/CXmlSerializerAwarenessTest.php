<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Serialization;

use ApiPlatform\Serializer\ItemNormalizer;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Serializer\Encoder\CXmlEncoder;
use Spryker\ApiPlatform\Serializer\Normalizer\CXmlNormalizer;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Guards the serializer wiring of the cXML decorators: both decorate a serializer-aware service
 * (`serializer.encoder.xml` / `api_platform.serializer.normalizer.item`) and therefore take its place in the
 * serializer chain. Without forwarding `setSerializer()` the decorated services cannot normalize object data,
 * which makes every `application/xml` API Platform request fail with a 500.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Serialization
 * @group CXmlSerializerAwarenessTest
 * Add your own group annotations below this line
 */
class CXmlSerializerAwarenessTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenObjectDataWhenSerializingToXmlThenDecoratedXmlEncoderNormalizesItThroughTheSerializer(): void
    {
        // Arrange
        $encoder = new CXmlEncoder(new XmlEncoder());
        $serializer = new Serializer([new ObjectNormalizer()], [$encoder]);

        // Act
        $xml = $serializer->serialize([new CXmlSerializerAwarenessFixture()], 'xml');

        // Assert
        $this->assertStringContainsString('<name>Imprint</name>', $xml);
    }

    public function testGivenEncoderWhenSerializerIsInjectedThenItIsForwardedToTheDecoratedXmlEncoder(): void
    {
        // Arrange
        $serializer = new Serializer();
        $xmlEncoderMock = $this->createMock(XmlEncoder::class);
        $encoder = new CXmlEncoder($xmlEncoderMock);

        // Assert
        $xmlEncoderMock
            ->expects($this->once())
            ->method('setSerializer')
            ->with($this->identicalTo($serializer));

        // Act
        $encoder->setSerializer($serializer);
    }

    public function testGivenNormalizerWhenSerializerIsInjectedThenItIsForwardedToTheDecoratedItemNormalizer(): void
    {
        // Arrange
        $serializer = new Serializer();
        $itemNormalizerMock = $this->createMock(ItemNormalizer::class);
        $normalizer = new CXmlNormalizer($itemNormalizerMock);

        // Assert
        $itemNormalizerMock
            ->expects($this->once())
            ->method('setSerializer')
            ->with($this->identicalTo($serializer));

        // Act
        $normalizer->setSerializer($serializer);
    }
}
