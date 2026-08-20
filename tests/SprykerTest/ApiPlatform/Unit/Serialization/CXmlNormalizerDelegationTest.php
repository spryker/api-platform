<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Serialization;

use ApiPlatform\Serializer\ItemNormalizer;
use Codeception\Test\Unit;
use CXml\Model\CXml;
use Spryker\ApiPlatform\Serializer\Normalizer\CXmlNormalizer;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Guards the support scope of the cXML normalizer: it decorates `api_platform.serializer.normalizer.item` and
 * therefore takes its place in the normalizer chain. Restricting support to the `xml` format leaves every other
 * format served by that normalizer (for example `csv`) to Symfony's plain object normalizer, which ignores API
 * Platform property metadata and exposes properties declared as `readable: false`.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Serialization
 * @group CXmlNormalizerDelegationTest
 * Add your own group annotations below this line
 */
class CXmlNormalizerDelegationTest extends Unit
{
    protected ApiUnitTester $tester;

    /**
     * @return array<array<string>>
     */
    public function formatDataProvider(): array
    {
        return [
            'xml' => ['xml'],
            'csv' => ['csv'],
            'jsonapi' => ['jsonapi'],
        ];
    }

    /**
     * @dataProvider formatDataProvider
     */
    public function testGivenAnyFormatWhenCheckingNormalizationSupportThenFollowsTheDecoratedItemNormalizer(string $format): void
    {
        // Arrange
        $resource = new CXmlSerializerAwarenessFixture();
        $itemNormalizerMock = $this->createMock(ItemNormalizer::class);
        $itemNormalizerMock
            ->method('supportsNormalization')
            ->with($resource, $format, [])
            ->willReturn(true);

        $normalizer = new CXmlNormalizer($itemNormalizerMock);

        // Act
        $isSupported = $normalizer->supportsNormalization($resource, $format);

        // Assert
        $this->assertTrue($isSupported);
    }

    /**
     * @dataProvider formatDataProvider
     */
    public function testGivenAnyFormatWhenGettingSupportedTypesThenFollowsTheDecoratedItemNormalizer(string $format): void
    {
        // Arrange
        $itemNormalizerMock = $this->createMock(ItemNormalizer::class);
        $itemNormalizerMock
            ->method('getSupportedTypes')
            ->with($format)
            ->willReturn(['object' => false]);

        $normalizer = new CXmlNormalizer($itemNormalizerMock);

        // Act
        $supportedTypes = $normalizer->getSupportedTypes($format);

        // Assert
        $this->assertSame(['object' => false], $supportedTypes);
    }

    public function testGivenDecodedCXmlDataWhenCheckingDenormalizationSupportThenSupportsItWithoutTheDecoratedItemNormalizer(): void
    {
        // Arrange
        $itemNormalizerMock = $this->createMock(ItemNormalizer::class);
        $normalizer = new CXmlNormalizer($itemNormalizerMock);

        // Assert
        $itemNormalizerMock
            ->expects($this->never())
            ->method('supportsDenormalization');

        // Act
        $isSupported = $normalizer->supportsDenormalization($this->createMock(CXml::class), CXml::class, 'xml');

        // Assert
        $this->assertTrue($isSupported);
    }

    public function testGivenNonCXmlDataWhenCheckingDenormalizationSupportThenFollowsTheDecoratedItemNormalizer(): void
    {
        // Arrange
        $itemNormalizerMock = $this->createMock(ItemNormalizer::class);
        $itemNormalizerMock
            ->method('supportsDenormalization')
            ->willReturn(false);

        $normalizer = new CXmlNormalizer($itemNormalizerMock);

        // Act
        $isSupported = $normalizer->supportsDenormalization(['name' => 'Imprint'], CXmlSerializerAwarenessFixture::class, 'csv');

        // Assert
        $this->assertFalse($isSupported);
    }
}
