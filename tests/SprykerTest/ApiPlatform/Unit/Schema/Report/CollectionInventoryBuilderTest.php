<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Report;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Report\CollectionInventoryBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Report
 * @group CollectionInventoryBuilderTest
 * Add your own group annotations below this line
 */
class CollectionInventoryBuilderTest extends Unit
{
    public function testGivenMixedCollectionShapesWhenBuildingThenClassifiesEachAndOmitsScalarLists(): void
    {
        // Arrange
        $mergedSchema = [
            'shortName' => 'products',
            'properties' => [
                'prices' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['grossAmount' => [], 'currency' => []]],
                ],
                'addresses' => ['type' => 'array', 'objectName' => 'Address'],
                'categories' => [
                    'type' => 'array',
                    'openapiContext' => ['items' => ['type' => 'object', 'properties' => ['categoryKey' => []]]],
                ],
                'abstractProducts' => [
                    'type' => 'array',
                    'openapiContext' => ['example' => [['sku' => '1', 'name' => 'a', 'url' => '/a']]],
                ],
                'mystery' => ['type' => 'array'],
                'skus' => ['type' => 'array', 'items' => ['type' => 'string']],
                'labels' => ['type' => 'array', 'openapiContext' => ['example' => ['new', 'sale']]],
            ],
        ];

        // Act
        $rows = (new CollectionInventoryBuilder())->build($mergedSchema, 'Storefront');

        // Assert — one row per adoptable list, deterministically ordered by property path.
        $this->assertSame(
            ['abstractProducts', 'addresses', 'categories', 'mystery', 'prices'],
            array_column($rows, 'property'),
        );
        $byProperty = array_column($rows, 'state', 'property');
        $this->assertSame('typed', $byProperty['prices']);
        $this->assertSame('canonical', $byProperty['addresses']);
        $this->assertSame('handwritten', $byProperty['categories']);
        $this->assertSame('example-only', $byProperty['abstractProducts']);
        $this->assertSame('unknown', $byProperty['mystery']);
        $this->assertSame(2, array_column($rows, 'itemKeyCount', 'property')['prices']);
        $this->assertSame(3, array_column($rows, 'itemKeyCount', 'property')['abstractProducts']);
        $this->assertSame('Storefront', $rows[0]['apiType']);
        $this->assertSame('products', $rows[0]['resource']);
    }

    public function testGivenNestedCollectionWhenBuildingThenRecordsDottedPropertyPath(): void
    {
        // Arrange
        $mergedSchema = [
            'shortName' => 'products',
            'properties' => [
                'stocks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'availabilities' => [
                                'type' => 'array',
                                'openapiContext' => ['example' => [['quantity' => 1]]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $rows = (new CollectionInventoryBuilder())->build($mergedSchema, 'Backend');

        // Assert
        $this->assertSame(['stocks', 'stocks.items.availabilities'], array_column($rows, 'property'));
        $this->assertSame('example-only', array_column($rows, 'state', 'property')['stocks.items.availabilities']);
    }

    public function testGivenListNestedInsideOpenapiContextItemsWhenBuildingThenClassifiesHandwrittenNeverTyped(): void
    {
        // Arrange — a sibling `items.properties` found *inside* an `openapiContext` block is never fed
        // to the real generator (`openapiContext` is copied verbatim), so it must classify the same as
        // its hand-described parent, not as `typed`, even though its shape looks exactly like a typed
        // collection in isolation.
        $mergedSchema = [
            'shortName' => 'product-offer-service-point-availabilities',
            'properties' => [
                'productOfferServicePointAvailabilities' => [
                    'type' => 'array',
                    'openapiContext' => [
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'productOfferServicePointAvailabilityResponseItems' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => ['quantity' => ['type' => 'integer']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $rows = (new CollectionInventoryBuilder())->build($mergedSchema, 'Storefront');

        // Assert
        $byProperty = array_column($rows, 'state', 'property');
        $this->assertSame('handwritten', $byProperty['productOfferServicePointAvailabilities']);
        $this->assertSame(
            'handwritten',
            $byProperty['productOfferServicePointAvailabilities.items.productOfferServicePointAvailabilityResponseItems'],
        );
        $this->assertNotContains('typed', $byProperty);
    }

    /**
     * @return array<string, array{property: array<string, mixed>, includes: array<int, array<string, mixed>>, expectedState: string}>
     */
    public function relationshipDetectionProvider(): array
    {
        $winningInclude = ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'];

        return [
            'auto-generated placeholder matching a winning include is relationship' => [
                'property' => ['type' => 'array', 'writable' => false, 'readable' => false],
                'includes' => [$winningInclude],
                'expectedState' => 'relationship',
            ],
            'writable property matching the include name is not relationship' => [
                'property' => ['type' => 'array', 'writable' => true, 'readable' => false],
                'includes' => [$winningInclude],
                'expectedState' => 'unknown',
            ],
            'readable:true property matching the include name is not relationship' => [
                'property' => ['type' => 'array', 'writable' => false, 'readable' => true],
                'includes' => [$winningInclude],
                'expectedState' => 'unknown',
            ],
            'resolver-based include never wins the slot, so not relationship' => [
                'property' => ['type' => 'array', 'writable' => false, 'readable' => false],
                'includes' => [$winningInclude + ['resolverClass' => 'Pyz\\Glue\\Customers\\Resolver\\AddressesResolver']],
                'expectedState' => 'unknown',
            ],
            'no matching include name is not relationship' => [
                'property' => ['type' => 'array', 'writable' => false, 'readable' => false],
                'includes' => [['relationshipName' => 'orders', 'targetResource' => 'CustomersOrders']],
                'expectedState' => 'unknown',
            ],
            'kebab-case include name matches its camelCase property counterpart' => [
                'property' => ['type' => 'array', 'writable' => false, 'readable' => false],
                'includes' => [['relationshipName' => 'product-prices', 'targetResource' => 'ProductPrices']],
                'expectedState' => 'relationship',
            ],
        ];
    }

    /**
     * @dataProvider relationshipDetectionProvider
     *
     * @param array<string, mixed> $property
     * @param array<int, array<string, mixed>> $includes
     */
    public function testGivenTopLevelArrayPropertyWhenBuildingThenClassifiesRelationshipPerIncludesCollisionRules(
        array $property,
        array $includes,
        string $expectedState,
    ): void {
        // Arrange — mirrors the case table `PropertyValidationRuleTest` uses for the same predicate
        // (`PropertyValidationRule::findWinningRelationshipInclude()`); the property name is always the
        // camelCase counterpart of the winning include's kebab-case `relationshipName`.
        $propertyName = str_contains($includes[0]['relationshipName'], 'product') ? 'productPrices' : 'addresses';
        $mergedSchema = [
            'shortName' => 'customers',
            'includes' => $includes,
            'properties' => [$propertyName => $property],
        ];

        // Act
        $rows = (new CollectionInventoryBuilder())->build($mergedSchema, 'Storefront');

        // Assert
        $this->assertSame($expectedState, array_column($rows, 'state', 'property')[$propertyName]);
    }

    public function testGivenNestedArrayPropertyMatchingAnIncludesNameWhenBuildingThenNeverClassifiesRelationship(): void
    {
        // Arrange — `includes` can only ever collide with a top-level property, so a nested property
        // sharing the same relationship-shaped name must not be misclassified as `relationship`.
        $mergedSchema = [
            'shortName' => 'customers',
            'includes' => [['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses']],
            'properties' => [
                'wrapper' => [
                    'type' => 'object',
                    'properties' => [
                        'addresses' => ['type' => 'array', 'writable' => false, 'readable' => false],
                    ],
                ],
            ],
        ];

        // Act
        $rows = (new CollectionInventoryBuilder())->build($mergedSchema, 'Storefront');

        // Assert
        $this->assertSame('unknown', array_column($rows, 'state', 'property')['wrapper.addresses']);
    }

    public function testGivenBothRealAndHandwrittenItemsWhenBuildingThenRecursesIntoTheRealItemsOnly(): void
    {
        // Arrange — a property describing its elements twice: a real `items` the generator reads, and a
        // duplicated `openapiContext.items` block. Both nest a list, and both would land on the same
        // `prices.items` path, so only the real one may contribute rows.
        $mergedSchema = [
            'name' => 'Products',
            'properties' => [
                'prices' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['tiers' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['amount' => []]]]],
                    ],
                    'openapiContext' => [
                        'items' => [
                            'type' => 'object',
                            'properties' => ['tiers' => ['type' => 'array']],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $rows = (new CollectionInventoryBuilder())->build($mergedSchema, 'Storefront');

        // Assert — one row for the nested list, classified from the real `items` block.
        $statesByProperty = array_column($rows, 'state', 'property');
        $this->assertSame(['prices', 'prices.items.tiers'], array_column($rows, 'property'));
        $this->assertSame('typed', $statesByProperty['prices.items.tiers']);
    }

    public function testGivenTwoResourcesSharingAShortNameWhenBuildingThenRowsCarryDistinctResources(): void
    {
        // Arrange — `/product-offers` and `/concrete-products/{}/product-offers` legitimately share a
        // `shortName`, but the report groups by `name`, so rows must stay attributable per merge unit.
        $properties = ['properties' => ['prices' => ['type' => 'array']]];
        $productOffers = ['name' => 'ProductOffers', 'shortName' => 'product-offers'] + $properties;
        $concreteProductsProductOffers = ['name' => 'ConcreteProductsProductOffers', 'shortName' => 'product-offers'] + $properties;

        // Act
        $builder = new CollectionInventoryBuilder();
        $rows = array_merge(
            $builder->build($productOffers, 'Storefront'),
            $builder->build($concreteProductsProductOffers, 'Storefront'),
        );

        // Assert — the row key (apiType, resource, property) stays unique across the two resources.
        $this->assertSame(['ProductOffers', 'ConcreteProductsProductOffers'], array_column($rows, 'resource'));
        $this->assertCount(2, array_unique(array_map(
            static fn (array $row): string => $row['apiType'] . '|' . $row['resource'] . '|' . $row['property'],
            $rows,
        )));
    }

    public function testGivenSchemaWithoutANameWhenBuildingThenFallsBackToTheShortName(): void
    {
        // Arrange
        $mergedSchema = ['shortName' => 'products', 'properties' => ['prices' => ['type' => 'array']]];

        // Act
        $rows = (new CollectionInventoryBuilder())->build($mergedSchema, 'Storefront');

        // Assert
        $this->assertSame('products', $rows[0]['resource']);
    }
}
