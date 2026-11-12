<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Security\Permission;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Security\Permission\OperationPermissionIdentifierResolver;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Security
 * @group Permission
 * @group OperationPermissionIdentifierResolverTest
 * Add your own group annotations below this line
 * @group SecurityAuthorization
 */
class OperationPermissionIdentifierResolverTest extends Unit
{
    protected const string KEY_ORDERS_GET_COLLECTION = 'App\\Orders|0|_api_/orders{._format}_get_collection';

    public function testGivenOneOperationPerKindWhenResolveIsCalledThenKindIsTheLastSegment(): void
    {
        // Arrange
        $collection = $this->createCollection('Carts', [
            '_api_/carts{._format}_post' => new Post(uriTemplate: '/carts'),
            '_api_/carts/{id}{._format}_get' => new Get(uriTemplate: '/carts/{id}'),
        ]);

        // Act
        $identifiers = (new OperationPermissionIdentifierResolver())->resolve('storefront', ['App\\Resource' => $collection]);

        // Assert
        $this->assertSame([
            'App\\Resource|0|_api_/carts{._format}_post' => 'api.storefront.carts.post',
            'App\\Resource|0|_api_/carts/{id}{._format}_get' => 'api.storefront.carts.get',
        ], $identifiers);
    }

    public function testGivenTwoCollectionsWithAndWithoutNameWhenResolveIsCalledThenEachGetsADisambiguator(): void
    {
        // Arrange
        $collection = $this->createCollection('availability-notifications', [
            '_api_/customers/{customerReference}/availability-notifications_get_collection' => new GetCollection(uriTemplate: '/customers/{customerReference}/availability-notifications'),
            'getMyAvailabilityNotifications' => new GetCollection(uriTemplate: '/my-availability-notifications', name: 'getMyAvailabilityNotifications'),
        ]);

        // Act
        $identifiers = array_values((new OperationPermissionIdentifierResolver())->resolve('storefront', ['App\\Resource' => $collection]));

        // Assert
        $this->assertSame([
            'api.storefront.availability-notifications.get-collection.customers-customer-reference-availability-notifications',
            'api.storefront.availability-notifications.get-collection.get-my-availability-notifications',
        ], $identifiers);
    }

    public function testGivenCamelCaseShortNameWhenResolveIsCalledThenResourceSegmentIsKebabCase(): void
    {
        // Arrange
        $collection = $this->createCollection('AbstractProductPrices', ['x' => new GetCollection(uriTemplate: '/abstract-product-prices')]);

        // Act
        $identifiers = array_values((new OperationPermissionIdentifierResolver())->resolve('backend', ['App\\Resource' => $collection]));

        // Assert
        $this->assertSame(['api.backend.abstract-product-prices.get-collection'], $identifiers);
    }

    public function testGivenASecondResourceWithTheSameShortNameAndKindWhenResolveIsCalledThenTheFirstIdentifierStaysTheSame(): void
    {
        // Arrange
        $orders = $this->createCollection('orders', ['_api_/orders{._format}_get_collection' => new GetCollection(uriTemplate: '/orders{._format}')], 'App\\Orders');
        $customerOrders = $this->createCollection('orders', ['_api_/customers/{customerReference}/orders_get_collection' => new GetCollection(uriTemplate: '/customers/{customerReference}/orders')], 'App\\CustomersOrders');
        $resolver = new OperationPermissionIdentifierResolver();

        // Act
        $identifiersAlone = $resolver->resolve('storefront', ['App\\Orders' => $orders]);
        $identifiersTogether = $resolver->resolve('storefront', [
            'App\\Orders' => $orders,
            'App\\CustomersOrders' => $customerOrders,
        ]);

        // Assert
        $this->assertSame('api.storefront.orders.get-collection', $identifiersAlone[static::KEY_ORDERS_GET_COLLECTION]);
        $this->assertSame($identifiersAlone[static::KEY_ORDERS_GET_COLLECTION], $identifiersTogether[static::KEY_ORDERS_GET_COLLECTION]);
    }

    public function testGivenTwoResourceClassesWithTheSameMethodAndUriWhenResolveIsCalledThenTheyShareOneIdentifier(): void
    {
        // Arrange
        $collections = [
            'App\\CartCodes' => $this->createCollection('cart-codes', ['_api_/cart-codes/{code}{._format}_get' => new Get(uriTemplate: '/cart-codes/{code}{._format}')], 'App\\CartCodes'),
            'App\\GuestCartCodes' => $this->createCollection('cart-codes', ['_api_/cart-codes/{code}{._format}_get' => new Get(uriTemplate: '/cart-codes/{code}{._format}')], 'App\\GuestCartCodes'),
        ];

        // Act
        $identifiers = array_values((new OperationPermissionIdentifierResolver())->resolve('storefront', $collections));

        // Assert
        $this->assertSame(['api.storefront.cart-codes.get', 'api.storefront.cart-codes.get'], $identifiers);
    }

    /**
     * @param array<string, \ApiPlatform\Metadata\HttpOperation> $operations
     */
    protected function createCollection(string $shortName, array $operations, string $resourceClass = 'App\\Resource'): ResourceMetadataCollection
    {
        $named = [];

        foreach ($operations as $name => $operation) {
            $named[$name] = $operation->withShortName($shortName)->withName($operation->getName() ?? $name);
        }

        return new ResourceMetadataCollection($resourceClass, [(new ApiResource(shortName: $shortName))->withOperations(new Operations($named))]);
    }
}
