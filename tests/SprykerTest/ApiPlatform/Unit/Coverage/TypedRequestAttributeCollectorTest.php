<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use ReflectionClass;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\TypedRequestAttribute;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\TypedRequestAttributesFixtureResource;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group TypedRequestAttributeCollectorTest
 * Add your own group annotations below this line
 */
class TypedRequestAttributeCollectorTest extends Unit
{
    protected const string DISPATCH_KEY_POST = 'POST /typed-request-attributes-fixture';

    protected const string DISPATCH_KEY_PATCH = 'PATCH /typed-request-attributes-fixture/{uuid}';

    public function testGivenAnEnumLikeNameWhenCollectingThenItIsTypedOnEveryInputOperation(): void
    {
        // Act
        $keys = $this->collectKeys();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  priceMode', $keys);
        $this->assertContains(static::DISPATCH_KEY_PATCH . '  priceMode', $keys);
    }

    public function testGivenADateLikeNameOrADateTypeWhenCollectingThenItIsTyped(): void
    {
        // Act
        $keys = $this->collectKeys();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  dateOfBirth', $keys);
        $this->assertContains(static::DISPATCH_KEY_POST . '  deliveryWindowStart', $keys);
    }

    public function testGivenAnOpenApiEnumWhenCollectingThenItIsTyped(): void
    {
        // Act
        $keys = $this->collectKeys();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  salutation', $keys);
    }

    public function testGivenAnUntypedOrAReadOnlyAttributeWhenCollectingThenItIsNotCollected(): void
    {
        // Act
        $keys = $this->collectKeys();

        // Assert
        $this->assertNotContains(static::DISPATCH_KEY_POST . '  name', $keys);
        $this->assertNotContains(static::DISPATCH_KEY_POST . '  currency', $keys);
    }

    public function testGivenATypedAttributeWhenCollectingThenItsExclusionKeyNamesTheResourceAndThePath(): void
    {
        // Act
        $typedRequestAttributes = $this->collect();

        // Assert
        $this->assertContains('typed-request-attributes-fixture.priceMode', array_map(
            static fn (TypedRequestAttribute $typedRequestAttribute): string => $typedRequestAttribute->exclusionKey(),
            $typedRequestAttributes,
        ));
    }

    /**
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\TypedRequestAttribute>
     */
    protected function collect(): array
    {
        return ContractCoverageFactory::createTypedRequestAttributeCollector()->collect(
            new ReflectionClass(TypedRequestAttributesFixtureResource::class),
            'typed-request-attributes-fixture',
            [
                ['operation' => new ApiOperation('POST', '/typed-request-attributes-fixture'), 'groups' => ['Default'], 'type' => 'Post'],
                ['operation' => new ApiOperation('PATCH', '/typed-request-attributes-fixture/{uuid}'), 'groups' => ['Default'], 'type' => 'Patch'],
            ],
        );
    }

    /**
     * @return array<string>
     */
    protected function collectKeys(): array
    {
        return array_map(static fn (TypedRequestAttribute $typedRequestAttribute): string => $typedRequestAttribute->key(), $this->collect());
    }
}
