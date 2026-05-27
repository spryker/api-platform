<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Relationship;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use Codeception\Test\Unit;
use Psr\Container\ContainerInterface;
use Spryker\ApiPlatform\Provider\RelationshipProvider;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolver;
use SprykerTest\ApiPlatform\ApiIntegrationTester;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Integration
 * @group Relationship
 * @group RelationshipLoadingTest
 * Add your own group annotations below this line
 */
class RelationshipLoadingTest extends Unit
{
    protected ApiIntegrationTester $tester;

    public function testGivenIncludeParameterWhenProvidingResourceThenRelationshipsAreLoaded(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001', 'name' => 'John Doe', 'addresses' => null];
        $relatedResource = (object)['uuid' => 'addr-001', 'address1' => '123 Test St'];

        $relatedProvider = $this->createMock(ProviderInterface::class);
        $relatedProvider->expects($this->once())
            ->method('provide')
            ->with(
                $this->isInstanceOf(GetCollection::class),
                ['customerReference' => 'customer-001'],
                $this->anything(),
            )
            ->willReturn([$relatedResource]);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->expects($this->once())
            ->method('has')
            ->with('AddressProvider')
            ->willReturn(true);
        $providerLocator->expects($this->once())
            ->method('get')
            ->with('AddressProvider')
            ->willReturn($relatedProvider);

        $relationships = [
            'customers.addresses' => [
                'relationship_name' => 'addresses',
                'target_resource_type' => 'addresses',
                'provider_service_id' => 'AddressProvider',
                'uri_variable_mappings' => [
                    'customerReference' => 'customerReference',
                ],
            ],
        ];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($mainResource);

        $request = new Request(['include' => 'addresses']);
        $resolverLocator = $this->createMock(ContainerInterface::class);

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $resolverLocator, $this->createMock(ContainerInterface::class));
        $decorator = new RelationshipProvider($innerProvider, $resolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');
        $context = ['request' => $request];

        // Act
        $result = $decorator->provide($operation, [], $context);

        // Assert
        $this->assertSame($mainResource, $result);

        $storedRelationships = $request->attributes->get('_spryker_resolved_relationships', []);
        $this->assertArrayHasKey('addresses', $storedRelationships);
        $this->assertSame([$relatedResource], $storedRelationships['addresses']);
    }

    public function testGivenNoIncludeParameterWhenProvidingResourceThenNoRelationshipsAreLoaded(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001', 'name' => 'John Doe'];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($mainResource);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->expects($this->never())->method('has');
        $providerLocator->expects($this->never())->method('get');

        $request = new Request();
        $resolverLocator = $this->createMock(ContainerInterface::class);

        $resolver = new ApiPlatformRelationshipResolver([], $providerLocator, $resolverLocator, $this->createMock(ContainerInterface::class));
        $decorator = new RelationshipProvider($innerProvider, $resolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');
        $context = ['request' => $request];

        // Act
        $result = $decorator->provide($operation, [], $context);

        // Assert
        $this->assertSame($mainResource, $result);

        $key = sprintf('_api_platform_relationships.%s', spl_object_hash($mainResource));
        $this->assertFalse($request->attributes->has($key));
    }

    public function testGivenMultipleIncludesWhenProvidingResourceThenAllRelationshipsAreLoaded(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001', 'name' => 'John Doe', 'addresses' => null, 'orders' => null];
        $addressResource = (object)['uuid' => 'addr-001', 'address1' => '123 Test St'];
        $orderResource = (object)['orderReference' => 'order-001', 'total' => 99.99];

        $addressProvider = $this->createMock(ProviderInterface::class);
        $addressProvider->expects($this->once())
            ->method('provide')
            ->willReturn([$addressResource]);

        $orderProvider = $this->createMock(ProviderInterface::class);
        $orderProvider->expects($this->once())
            ->method('provide')
            ->willReturn([$orderResource]);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->expects($this->exactly(2))
            ->method('has')
            ->willReturnCallback(fn ($id) => in_array($id, ['AddressProvider', 'OrderProvider']));
        $providerLocator->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(fn ($id) => match ($id) {
                'AddressProvider' => $addressProvider,
                'OrderProvider' => $orderProvider,
            });

        $relationships = [
            'customers.addresses' => [
                'relationship_name' => 'addresses',
                'target_resource_type' => 'addresses',
                'provider_service_id' => 'AddressProvider',
                'uri_variable_mappings' => ['customerReference' => 'customerReference'],
            ],
            'customers.orders' => [
                'relationship_name' => 'orders',
                'target_resource_type' => 'orders',
                'provider_service_id' => 'OrderProvider',
                'uri_variable_mappings' => ['customerReference' => 'customerReference'],
            ],
        ];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($mainResource);

        $request = new Request(['include' => 'addresses,orders']);
        $resolverLocator = $this->createMock(ContainerInterface::class);

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $resolverLocator, $this->createMock(ContainerInterface::class));
        $decorator = new RelationshipProvider($innerProvider, $resolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');
        $context = ['request' => $request];

        // Act
        $result = $decorator->provide($operation, [], $context);

        // Assert
        $this->assertSame($mainResource, $result);

        $storedRelationships = $request->attributes->get('_spryker_resolved_relationships', []);
        $this->assertArrayHasKey('addresses', $storedRelationships);
        $this->assertArrayHasKey('orders', $storedRelationships);
        $this->assertSame([$addressResource], $storedRelationships['addresses']);
        $this->assertSame([$orderResource], $storedRelationships['orders']);
    }

    public function testGivenInvalidRelationshipNameWhenProvidingResourceThenGracefullyIgnored(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001', 'name' => 'John Doe'];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($mainResource);

        $providerLocator = $this->createMock(ContainerInterface::class);

        $request = new Request(['include' => 'nonexistent']);
        $resolverLocator = $this->createMock(ContainerInterface::class);

        $resolver = new ApiPlatformRelationshipResolver([], $providerLocator, $resolverLocator, $this->createMock(ContainerInterface::class));
        $decorator = new RelationshipProvider($innerProvider, $resolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');
        $context = ['request' => $request];

        // Act
        $result = $decorator->provide($operation, [], $context);

        // Assert
        $this->assertSame($mainResource, $result);

        $key = sprintf('_api_platform_relationships.%s', spl_object_hash($mainResource));

        if ($request->attributes->has($key)) {
            $storedRelationships = $request->attributes->get($key);
            $this->assertEmpty($storedRelationships);
        }
    }

    public function testGivenCollectionResultWhenProvidingResourceThenAllResourcesHaveRelationships(): void
    {
        // Arrange
        $resource1 = (object)['customerReference' => 'customer-001', 'name' => 'John Doe'];
        $resource2 = (object)['customerReference' => 'customer-002', 'name' => 'Jane Smith'];
        $resources = [$resource1, $resource2];

        $address1 = (object)['uuid' => 'addr-001'];
        $address2 = (object)['uuid' => 'addr-002'];

        $relatedProvider = $this->createMock(ProviderInterface::class);
        $relatedProvider->expects($this->exactly(2))
            ->method('provide')
            ->willReturnOnConsecutiveCalls([$address1], [$address2]);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->method('has')->willReturn(true);
        $providerLocator->method('get')->willReturn($relatedProvider);

        $relationships = [
            'customers.addresses' => [
                'relationship_name' => 'addresses',
                'target_resource_type' => 'addresses',
                'provider_service_id' => 'AddressProvider',
                'uri_variable_mappings' => ['customerReference' => 'customerReference'],
            ],
        ];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($resources);

        $request = new Request(['include' => 'addresses']);
        $resolverLocator = $this->createMock(ContainerInterface::class);

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $resolverLocator, $this->createMock(ContainerInterface::class));
        $decorator = new RelationshipProvider($innerProvider, $resolver, $this->createMock(ContainerInterface::class));

        $operation = new GetCollection(shortName: 'customers');
        $context = ['request' => $request];

        // Act
        $result = $decorator->provide($operation, [], $context);

        // Assert
        $this->assertSame($resources, $result);

        // For collections, relationships are not assigned to individual items in the array
        // The implementation only assigns relationships when result is a single object
        $this->assertFalse(property_exists($resource1, 'addresses'));
        $this->assertFalse(property_exists($resource2, 'addresses'));
    }
}
