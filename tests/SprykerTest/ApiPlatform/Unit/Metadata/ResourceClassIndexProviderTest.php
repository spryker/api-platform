<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Metadata;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Metadata\ResourceClassIndexProvider;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Guards the code bucket selection rule applied to the compiled resource class index:
 * base entries fill the index, entries of the current code bucket override the same short name,
 * and entries of other code buckets are ignored.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Metadata
 * @group ResourceClassIndexProviderTest
 * Add your own group annotations below this line
 */
class ResourceClassIndexProviderTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenIndexEntriesWithCodeBucketVariantsWhenResolvingThenVariantOverridesBaseAndForeignBucketIsIgnored(): void
    {
        // Arrange
        $resourceClassIndexProvider = new ResourceClassIndexProvider([
            'Generated\\Api\\Backend\\StoresBackendResource' => [
                '' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresBackendResource', 'includedSortPriority' => null],
                'EU' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresEUBackendResource', 'includedSortPriority' => null],
                'US' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresUSBackendResource', 'includedSortPriority' => null],
            ],
            'Generated\\Api\\Backend\\ItemsBackendResource' => [
                '' => ['shortName' => 'items', 'className' => 'Generated\\Api\\Backend\\ItemsBackendResource', 'includedSortPriority' => 100],
            ],
        ]);

        // Act
        $resourceClassIndex = $resourceClassIndexProvider->getResourceClassIndex('EU');
        $includedSortPriorityIndex = $resourceClassIndexProvider->getIncludedSortPriorityIndex('EU');

        // Assert
        $this->assertSame([
            'stores' => 'Generated\\Api\\Backend\\StoresEUBackendResource',
            'items' => 'Generated\\Api\\Backend\\ItemsBackendResource',
        ], $resourceClassIndex);
        $this->assertSame(['items' => 100], $includedSortPriorityIndex);
    }

    public function testGivenIndexEntriesWhenResolvingWithoutCodeBucketThenOnlyBaseEntriesAreUsed(): void
    {
        // Arrange
        $resourceClassIndexProvider = new ResourceClassIndexProvider([
            'Generated\\Api\\Backend\\StoresBackendResource' => [
                '' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresBackendResource', 'includedSortPriority' => null],
                'EU' => ['shortName' => 'stores', 'className' => 'Generated\\Api\\Backend\\StoresEUBackendResource', 'includedSortPriority' => null],
            ],
        ]);

        // Act
        $resourceClassIndex = $resourceClassIndexProvider->getResourceClassIndex('');

        // Assert
        $this->assertSame(['stores' => 'Generated\\Api\\Backend\\StoresBackendResource'], $resourceClassIndex);
    }

    public function testGivenDistinctResourcesSharingShortNameWhenResolvingThenLastCompiledEntryWinsForClassAndPriority(): void
    {
        // Arrange: `orders` is the JSON:API short name of both the CustomersOrders and the Orders
        // resource, so a short-name-keyed map can hold only one of them.
        $resourceClassIndexProvider = new ResourceClassIndexProvider([
            'Generated\\Api\\Storefront\\CustomersOrdersStorefrontResource' => [
                '' => ['shortName' => 'orders', 'className' => 'Generated\\Api\\Storefront\\CustomersOrdersStorefrontResource', 'includedSortPriority' => 100],
            ],
            'Generated\\Api\\Storefront\\OrdersStorefrontResource' => [
                '' => ['shortName' => 'orders', 'className' => 'Generated\\Api\\Storefront\\OrdersStorefrontResource', 'includedSortPriority' => null],
            ],
        ]);

        // Act
        $resourceClassIndex = $resourceClassIndexProvider->getResourceClassIndex('');
        $includedSortPriorityIndex = $resourceClassIndexProvider->getIncludedSortPriorityIndex('');

        // Assert
        $this->assertSame(['orders' => 'Generated\\Api\\Storefront\\OrdersStorefrontResource'], $resourceClassIndex);
        $this->assertSame([], $includedSortPriorityIndex);
    }

    public function testGivenVariantWithoutSortPriorityWhenResolvingThenBaseSortPriorityIsDiscarded(): void
    {
        // Arrange: the variant fully replaces the base entry, including its priority.
        $resourceClassIndexProvider = new ResourceClassIndexProvider([
            'Generated\\Api\\Backend\\ItemsBackendResource' => [
                '' => ['shortName' => 'items', 'className' => 'Generated\\Api\\Backend\\ItemsBackendResource', 'includedSortPriority' => 100],
                'EU' => ['shortName' => 'items', 'className' => 'Generated\\Api\\Backend\\ItemsEUBackendResource', 'includedSortPriority' => null],
            ],
        ]);

        // Act
        $includedSortPriorityIndex = $resourceClassIndexProvider->getIncludedSortPriorityIndex('EU');

        // Assert
        $this->assertSame([], $includedSortPriorityIndex);
    }
}
