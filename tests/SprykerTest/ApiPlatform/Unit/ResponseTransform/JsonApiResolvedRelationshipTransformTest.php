<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\ResponseTransform;

use Codeception\Test\Unit;
use ReflectionMethod;
use Spryker\ApiPlatform\Metadata\ResourceClassIndexProviderInterface;
use Spryker\ApiPlatform\ResponseTransform\JsonApiResolvedRelationshipTransform;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group ResponseTransform
 * @group JsonApiResolvedRelationshipTransformTest
 * Add your own group annotations below this line
 */
class JsonApiResolvedRelationshipTransformTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenSameResourceObjectWhenNormalizedTwiceThenNormalizerRunsOnce(): void
    {
        // Arrange
        $resource = $this->createResource();
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->expects($this->once())->method('normalize')->willReturn($this->createNormalizedPayload());
        $transform = new JsonApiResolvedRelationshipTransform($normalizer, $this->createMock(ResourceClassIndexProviderInterface::class));

        // Act
        $first = $this->normalizeRelatedResource($transform, $resource);
        $second = $this->normalizeRelatedResource($transform, $resource);

        // Assert
        $this->assertSame($first, $second);
    }

    public function testGivenDistinctResourceObjectsWhenNormalizedThenEachObjectIsNormalizedSeparately(): void
    {
        // Arrange
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->expects($this->exactly(2))->method('normalize')->willReturn($this->createNormalizedPayload());
        $transform = new JsonApiResolvedRelationshipTransform($normalizer, $this->createMock(ResourceClassIndexProviderInterface::class));

        // Act & Assert: two objects of the same class do not share a memo entry
        $this->normalizeRelatedResource($transform, $this->createResource());
        $this->normalizeRelatedResource($transform, $this->createResource());
    }

    protected function normalizeRelatedResource(JsonApiResolvedRelationshipTransform $transform, object $resource): ?array
    {
        return (new ReflectionMethod($transform, 'normalizeRelatedResource'))->invoke($transform, $resource);
    }

    protected function createResource(): object
    {
        return new class {
            public ?string $name = 'test-name';
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function createNormalizedPayload(): array
    {
        return ['data' => ['type' => 'test-resources', 'id' => 'abc-1', 'attributes' => ['name' => 'test-name']]];
    }
}
