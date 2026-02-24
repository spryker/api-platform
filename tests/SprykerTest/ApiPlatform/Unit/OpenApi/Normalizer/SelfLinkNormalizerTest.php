<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\Normalizer;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\OpenApi\Normalizer\SelfLinkNormalizer;
use stdClass;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group Normalizer
 * @group SelfLinkNormalizerTest
 * Add your own group annotations below this line
 */
class SelfLinkNormalizerTest extends Unit
{
    public function testGivenGenIdFalseWhenNormalizingThenSkipsSelfLinkGeneration(): void
    {
        // Arrange
        $object = new stdClass();
        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->willReturn([
                'data' => [
                    'type' => 'tokens',
                    'id' => 'jwt-token-value',
                    'attributes' => ['tokenType' => 'Bearer'],
                ],
            ]);

        $normalizer = new SelfLinkNormalizer();
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', [
            'output' => ['gen_id' => false],
        ]);

        // Assert
        $this->assertArrayNotHasKey('links', $result['data']);
    }

    public function testGivenGenIdTrueWhenNormalizingThenAddsSelfLink(): void
    {
        // Arrange
        $object = new stdClass();
        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->willReturn([
                'data' => [
                    'type' => 'customers',
                    'id' => 'customer-123',
                ],
            ]);

        $normalizer = new SelfLinkNormalizer();
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', []);

        // Assert
        $this->assertArrayHasKey('links', $result['data']);
        $this->assertEquals('customer-123', $result['data']['links']['self']);
    }

    public function testGivenGenIdMissingWhenNormalizingThenAddsSelfLink(): void
    {
        // Arrange
        $object = new stdClass();
        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->willReturn([
                'data' => [
                    'type' => 'items',
                    'id' => 'item-456',
                ],
            ]);

        $normalizer = new SelfLinkNormalizer();
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', [
            'output' => [],
        ]);

        // Assert
        $this->assertArrayHasKey('links', $result['data']);
        $this->assertEquals('item-456', $result['data']['links']['self']);
    }
}
