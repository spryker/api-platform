<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Metadata\PermissionCheckResourceMetadataCollectionFactory;
use Spryker\ApiPlatform\Security\Permission\OperationPermissionIdentifierResolver;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Metadata
 * @group PermissionCheckResourceMetadataCollectionFactoryTest
 * Add your own group annotations below this line
 * @group SecurityAuthorization
 */
class PermissionCheckResourceMetadataCollectionFactoryTest extends Unit
{
    public function testGivenOperationWithOwnSecurityWhenCreateIsCalledThenIdentifierCheckIsAndedAndBaselineIsKept(): void
    {
        // Arrange
        $factory = $this->createFactory(new ApiResource(
            shortName: 'carts',
            operations: ['post' => (new Post(uriTemplate: '/carts', security: "is_granted('ROLE_CUSTOMER')"))->withShortName('carts')->withName('post')],
        ));

        // Act
        $operation = iterator_to_array($factory->create('App\\Cart')[0]->getOperations())['post'];

        // Assert
        $this->assertSame("(is_granted('ROLE_CUSTOMER')) and is_granted('api.storefront.carts.post')", $operation->getSecurity());
        $this->assertSame('api.storefront.carts.post', $operation->getExtraProperties()[OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER]);
        $this->assertSame("is_granted('ROLE_CUSTOMER')", $operation->getExtraProperties()[OperationPermissionIdentifierResolver::EXTRA_PROPERTY_BASELINE_SECURITY]);
    }

    public function testGivenOnlyResourceLevelSecurityWhenCreateIsCalledThenItIsInheritedIntoTheOperation(): void
    {
        // Arrange
        $factory = $this->createFactory(new ApiResource(
            shortName: 'checkout',
            security: "is_granted('CUSTOMER_ACCESS')",
            operations: ['post' => (new Post(uriTemplate: '/checkout'))->withShortName('checkout')->withName('post')],
        ));

        // Act
        $resource = $factory->create('App\\Checkout')[0];

        // Assert
        $this->assertSame("(is_granted('CUSTOMER_ACCESS')) and is_granted('api.storefront.checkout.post')", iterator_to_array($resource->getOperations())['post']->getSecurity());
        $this->assertSame("is_granted('CUSTOMER_ACCESS')", $resource->getSecurity());
    }

    public function testGivenNoSecurityWhenCreateIsCalledThenOnlyIdentifierCheckIsSet(): void
    {
        // Arrange
        $factory = $this->createFactory(new ApiResource(
            shortName: 'catalog-search',
            operations: ['get' => (new Get(uriTemplate: '/catalog-search/{id}'))->withShortName('catalog-search')->withName('get')],
        ));

        // Act
        $operation = iterator_to_array($factory->create('App\\Search')[0]->getOperations())['get'];

        // Assert
        $this->assertSame("is_granted('api.storefront.catalog-search.get')", $operation->getSecurity());
        $this->assertNull($operation->getExtraProperties()[OperationPermissionIdentifierResolver::EXTRA_PROPERTY_BASELINE_SECURITY]);
    }

    public function testGivenTwoResourceClassesWithTheSameShortNameAndKindWhenCreateIsCalledThenTheirIdentifiersDiffer(): void
    {
        // Arrange
        $factory = $this->createFactoryForResources([
            'App\\Orders' => new ApiResource(
                shortName: 'orders',
                operations: ['_api_/orders_get_collection' => (new GetCollection(uriTemplate: '/orders'))->withShortName('orders')->withName('_api_/orders_get_collection')],
            ),
            'App\\CustomersOrders' => new ApiResource(
                shortName: 'orders',
                operations: ['_api_/customers/{customerReference}/orders_get_collection' => (new GetCollection(uriTemplate: '/customers/{customerReference}/orders'))->withShortName('orders')->withName('_api_/customers/{customerReference}/orders_get_collection')],
            ),
        ]);

        // Act
        $ordersOperation = iterator_to_array($factory->create('App\\Orders')[0]->getOperations())['_api_/orders_get_collection'];
        $customersOrdersOperation = iterator_to_array($factory->create('App\\CustomersOrders')[0]->getOperations())['_api_/customers/{customerReference}/orders_get_collection'];

        // Assert
        $this->assertSame('api.storefront.orders.get-collection', $ordersOperation->getExtraProperties()[OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER]);
        $this->assertSame('api.storefront.orders.get-collection.customers-customer-reference-orders', $customersOrdersOperation->getExtraProperties()[OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER]);
    }

    protected function createFactory(ApiResource $apiResource): PermissionCheckResourceMetadataCollectionFactory
    {
        return $this->createFactoryForResources(['App\\Cart' => $apiResource, 'App\\Checkout' => $apiResource, 'App\\Search' => $apiResource]);
    }

    /**
     * @param array<string, \ApiPlatform\Metadata\ApiResource> $apiResources Keyed by resource class.
     */
    protected function createFactoryForResources(array $apiResources): PermissionCheckResourceMetadataCollectionFactory
    {
        $decorated = new class ($apiResources) implements ResourceMetadataCollectionFactoryInterface {
            /**
             * @param array<string, \ApiPlatform\Metadata\ApiResource> $apiResources
             */
            public function __construct(protected array $apiResources)
            {
            }

            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($resourceClass, [$this->apiResources[$resourceClass]]);
            }
        };
        $resourceNameCollectionFactory = new class (array_keys($apiResources)) implements ResourceNameCollectionFactoryInterface {
            /**
             * @param list<string> $resourceClasses
             */
            public function __construct(protected array $resourceClasses)
            {
            }

            public function create(): ResourceNameCollection
            {
                return new ResourceNameCollection($this->resourceClasses);
            }
        };

        return new PermissionCheckResourceMetadataCollectionFactory($decorated, $resourceNameCollectionFactory, new OperationPermissionIdentifierResolver(), ['storefront']);
    }
}
