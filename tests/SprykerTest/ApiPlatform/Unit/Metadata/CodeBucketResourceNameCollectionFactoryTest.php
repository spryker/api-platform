<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Codeception\Test\Unit;
use ReflectionMethod;
use Spryker\ApiPlatform\Exception\ResourceClassIndexException;
use Spryker\ApiPlatform\Metadata\CodeBucketResourceNameCollectionFactory;
use SprykerTest\ApiPlatform\ApiUnitTester;
use stdClass;

/**
 * Guards the memoization of the filtered resource name collection: the decorator sits outside
 * API Platform's metadata cache and `ResourceClassResolver` calls `create()` on every uncached
 * class lookup, so without memoization the filtering re-runs many times per request.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Metadata
 * @group CodeBucketResourceNameCollectionFactoryTest
 * Add your own group annotations below this line
 */
class CodeBucketResourceNameCollectionFactoryTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenRepeatedCreateCallsWhenCreatingThenDecoratedFactoryIsCalledOnceAndResultIsMemoized(): void
    {
        // Arrange
        $decoratedFactoryMock = $this->createMock(ResourceNameCollectionFactoryInterface::class);
        $decoratedFactoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn(new ResourceNameCollection([stdClass::class]));

        $codeBucketResourceNameCollectionFactory = new CodeBucketResourceNameCollectionFactory($decoratedFactoryMock);

        // Act
        $firstResult = $codeBucketResourceNameCollectionFactory->create();
        $secondResult = $codeBucketResourceNameCollectionFactory->create();

        // Assert
        $this->assertSame($firstResult, $secondResult);
        $this->assertSame([stdClass::class], iterator_to_array($firstResult));
    }

    public function testGivenIndexEntriesWithCodeBucketVariantsWhenCreatingThenVariantReplacesBaseAndUnindexedClassesPassThrough(): void
    {
        // Arrange
        $decoratedFactoryMock = $this->createMock(ResourceNameCollectionFactoryInterface::class);
        $decoratedFactoryMock->method('create')->willReturn(new ResourceNameCollection([
            'Generated\\Api\\Backend\\StoresBackendResource',
            'Generated\\Api\\Backend\\StoresEUBackendResource',
            'Generated\\Api\\Backend\\StoresUSBackendResource',
            stdClass::class,
        ]));

        $codeBucketResourceNameCollectionFactory = new CodeBucketResourceNameCollectionFactory($decoratedFactoryMock, [
            'Generated\\Api\\Backend\\StoresBackendResource' => [
                '' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresBackendResource', 'includedSortPriority' => null],
                'EU' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresEUBackendResource', 'includedSortPriority' => null],
                'US' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresUSBackendResource', 'includedSortPriority' => null],
            ],
        ]);

        // Act
        $resourceNameCollection = $this->createForCodeBucket($codeBucketResourceNameCollectionFactory, 'EU');

        // Assert: only the EU variant of the indexed resource survives; the unindexed class is untouched.
        $this->assertSame([
            'Generated\\Api\\Backend\\StoresEUBackendResource',
            stdClass::class,
        ], iterator_to_array($resourceNameCollection));
    }

    public function testGivenDistinctResourcesSharingShortNameWhenCreatingThenBothSurvive(): void
    {
        // Arrange: `orders` is the JSON:API short name of both the Orders and the CustomersOrders
        // resource — selection must group by base class, not by short name (regression: the
        // shortName-keyed selection dropped CustomersOrders and its routes returned 404).
        $decoratedFactoryMock = $this->createMock(ResourceNameCollectionFactoryInterface::class);
        $decoratedFactoryMock->method('create')->willReturn(new ResourceNameCollection([
            'Generated\\Api\\Storefront\\CustomersOrdersStorefrontResource',
            'Generated\\Api\\Storefront\\OrdersStorefrontResource',
        ]));

        $codeBucketResourceNameCollectionFactory = new CodeBucketResourceNameCollectionFactory($decoratedFactoryMock, [
            'Generated\\Api\\Storefront\\CustomersOrdersStorefrontResource' => [
                '' => ['shortName' => 'orders', 'className' => 'Generated\\Api\\Storefront\\CustomersOrdersStorefrontResource', 'includedSortPriority' => null],
            ],
            'Generated\\Api\\Storefront\\OrdersStorefrontResource' => [
                '' => ['shortName' => 'orders', 'className' => 'Generated\\Api\\Storefront\\OrdersStorefrontResource', 'includedSortPriority' => null],
            ],
        ]);

        // Act
        $resourceNameCollection = $this->createForCodeBucket($codeBucketResourceNameCollectionFactory, '');

        // Assert
        $this->assertSame([
            'Generated\\Api\\Storefront\\CustomersOrdersStorefrontResource',
            'Generated\\Api\\Storefront\\OrdersStorefrontResource',
        ], iterator_to_array($resourceNameCollection));
    }

    public function testGivenEmptyIndexAndGeneratedResourceClassWhenCreatingThenExceptionIsThrown(): void
    {
        // Arrange: an empty index next to generated classes means the container was compiled
        // before api:generate — silently serving every code bucket variant must fail loud instead.
        $decoratedFactoryMock = $this->createMock(ResourceNameCollectionFactoryInterface::class);
        $decoratedFactoryMock->method('create')->willReturn(new ResourceNameCollection([
            'Generated\\Api\\Storefront\\OrdersStorefrontResource',
        ]));

        $codeBucketResourceNameCollectionFactory = new CodeBucketResourceNameCollectionFactory($decoratedFactoryMock, []);

        // Assert
        $this->expectException(ResourceClassIndexException::class);

        // Act
        $this->createForCodeBucket($codeBucketResourceNameCollectionFactory, '');
    }

    public function testGivenIndexEntriesWhenCreatingWithoutCodeBucketThenOnlyBaseResourcesSurvive(): void
    {
        // Arrange
        $decoratedFactoryMock = $this->createMock(ResourceNameCollectionFactoryInterface::class);
        $decoratedFactoryMock->method('create')->willReturn(new ResourceNameCollection([
            'Generated\\Api\\Backend\\StoresBackendResource',
            'Generated\\Api\\Backend\\StoresEUBackendResource',
        ]));

        $codeBucketResourceNameCollectionFactory = new CodeBucketResourceNameCollectionFactory($decoratedFactoryMock, [
            'Generated\\Api\\Backend\\StoresBackendResource' => [
                '' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresBackendResource', 'includedSortPriority' => null],
                'EU' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresEUBackendResource', 'includedSortPriority' => null],
            ],
        ]);

        // Act
        $resourceNameCollection = $this->createForCodeBucket($codeBucketResourceNameCollectionFactory, '');

        // Assert
        $this->assertSame(['Generated\\Api\\Backend\\StoresBackendResource'], iterator_to_array($resourceNameCollection));
    }

    protected function createForCodeBucket(
        CodeBucketResourceNameCollectionFactory $codeBucketResourceNameCollectionFactory,
        string $codeBucket,
    ): ResourceNameCollection {
        // The current code bucket comes from the APPLICATION_CODE_BUCKET environment constant,
        // so the protected filter method is invoked directly to test bucket-specific behavior.
        $reflectionMethod = new ReflectionMethod($codeBucketResourceNameCollectionFactory, 'createForCodeBucket');

        return $reflectionMethod->invoke($codeBucketResourceNameCollectionFactory, $codeBucket);
    }
}
