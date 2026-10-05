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
use Spryker\ApiPlatform\Contract\Coverage\RequestAttribute;
use Spryker\ApiPlatform\Contract\Coverage\RequestAttributeTruthCollector;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\RequestAttributesFixtureResource;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\RequestAttributesFixtureSalesUnitObject;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group RequestAttributeTruthCollectorTest
 * Add your own group annotations below this line
 */
class RequestAttributeTruthCollectorTest extends Unit
{
    protected const string DISPATCH_KEY_POST = 'POST /request-attributes-fixture';

    protected const string DISPATCH_KEY_PATCH = 'PATCH /request-attributes-fixture/{uuid}';

    public function testGivenWritableScalarPropertiesWhenCollectingThenEachIsOnePathPerInputOperation(): void
    {
        // Act
        $paths = $this->collectPaths();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  name', $paths);
        $this->assertContains(static::DISPATCH_KEY_PATCH . '  name', $paths);
    }

    public function testGivenAReadOnlyOrIdentifierPropertyWhenCollectingThenItIsSkipped(): void
    {
        // Act
        $paths = $this->collectPaths();

        // Assert
        $this->assertNotContains(static::DISPATCH_KEY_POST . '  createdAt', $paths);
        $this->assertNotContains(static::DISPATCH_KEY_POST . '  uuid', $paths);
    }

    public function testGivenWritableOnPostWhenCollectingForPatchThenThePropertyIsSkipped(): void
    {
        // Act
        $paths = $this->collectPaths();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  store', $paths);
        $this->assertNotContains(static::DISPATCH_KEY_PATCH . '  store', $paths);
    }

    public function testGivenANestedValueObjectWhenCollectingThenEachWritableChildIsAPath(): void
    {
        // Act
        $paths = $this->collectPaths();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  salesUnit.id', $paths);
        $this->assertContains(static::DISPATCH_KEY_POST . '  salesUnit.amount', $paths);
        $this->assertNotContains(static::DISPATCH_KEY_POST . '  salesUnit.name', $paths);
        $this->assertNotContains(static::DISPATCH_KEY_POST . '  salesUnit', $paths);
    }

    public function testGivenACollectionConstraintWhenCollectingThenEachFieldIsAPathIncludingOptionalOnes(): void
    {
        // Act
        $paths = $this->collectPaths();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  configuration.isComplete', $paths);
        $this->assertContains(static::DISPATCH_KEY_POST . '  configuration.displayData', $paths);
    }

    public function testGivenAnAllWrappedCollectionWhenCollectingThenFieldsUseTheListWildcard(): void
    {
        // Act
        $paths = $this->collectPaths();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  shipments[].items', $paths);
        $this->assertContains(static::DISPATCH_KEY_POST . '  shipments[].requestedDeliveryDate', $paths);
    }

    public function testGivenACollectionDeeperThanOneLevelWhenCollectingThenItCollapsesToThePresencePath(): void
    {
        // Act
        $paths = $this->collectPaths();

        // Assert
        $this->assertContains(static::DISPATCH_KEY_POST . '  configuration.prices', $paths);
        $this->assertSame([], array_values(array_filter($paths, static fn (string $path): bool => str_contains($path, 'currency'))));
    }

    /**
     * @return array<string>
     */
    protected function collectPaths(): array
    {
        $collector = new RequestAttributeTruthCollector(
            static fn (string $class): bool => $class === RequestAttributesFixtureSalesUnitObject::class,
        );

        return array_map(
            static fn (RequestAttribute $requestAttribute): string => $requestAttribute->key(),
            $collector->collect(new ReflectionClass(RequestAttributesFixtureResource::class), [
                ['operation' => new ApiOperation('POST', '/request-attributes-fixture'), 'type' => 'Post'],
                ['operation' => new ApiOperation('PATCH', '/request-attributes-fixture/{uuid}'), 'type' => 'Patch'],
            ]),
        );
    }
}
