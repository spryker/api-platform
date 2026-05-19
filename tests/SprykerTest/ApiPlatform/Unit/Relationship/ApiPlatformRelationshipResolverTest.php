<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Relationship;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use Codeception\Test\Unit;
use Psr\Container\ContainerInterface;
use Spryker\ApiPlatform\Provider\BatchLoadableProviderInterface;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Relationship
 * @group ApiPlatformRelationshipResolverTest
 * Add your own group annotations below this line
 */
class ApiPlatformRelationshipResolverTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenNoIncludeParameterWhenParsingIncludeParameterThenReturnsEmptyArray(): void
    {
        // Arrange
        $resolver = new ApiPlatformRelationshipResolver([], $this->createMock(ContainerInterface::class), $this->createMock(ContainerInterface::class));
        $context = ['request' => new Request()];

        // Act
        $result = $resolver->parseIncludeParameter($context);

        // Assert
        $this->assertSame([], $result);
    }

    public function testGivenSingleIncludeParameterWhenParsingIncludeParameterThenReturnsSingleValue(): void
    {
        // Arrange
        $resolver = new ApiPlatformRelationshipResolver([], $this->createMock(ContainerInterface::class), $this->createMock(ContainerInterface::class));
        $request = new Request(['include' => 'addresses']);
        $context = ['request' => $request];

        // Act
        $result = $resolver->parseIncludeParameter($context);

        // Assert
        $this->assertSame(['addresses'], $result);
    }

    public function testGivenMultipleIncludeParameterWhenParsingIncludeParameterThenReturnsMultipleValues(): void
    {
        // Arrange
        $resolver = new ApiPlatformRelationshipResolver([], $this->createMock(ContainerInterface::class), $this->createMock(ContainerInterface::class));
        $request = new Request(['include' => 'addresses,orders,wishlists']);
        $context = ['request' => $request];

        // Act
        $result = $resolver->parseIncludeParameter($context);

        // Assert
        $this->assertSame(['addresses', 'orders', 'wishlists'], $result);
    }

    public function testGivenNestedIncludeParameterWhenParsingIncludeParameterThenFlattensNestedIncludes(): void
    {
        // Arrange
        $resolver = new ApiPlatformRelationshipResolver([], $this->createMock(ContainerInterface::class), $this->createMock(ContainerInterface::class));
        $request = new Request(['include' => 'addresses.country,orders.items']);
        $context = ['request' => $request];

        // Act
        $result = $resolver->parseIncludeParameter($context);

        // Assert
        $this->assertSame(['addresses', 'addresses.country', 'orders', 'orders.items'], $result);
    }

    public function testGivenMixedIncludesWhenParsingIncludeParameterThenReturnsAllIncludesFlattened(): void
    {
        // Arrange
        $resolver = new ApiPlatformRelationshipResolver([], $this->createMock(ContainerInterface::class), $this->createMock(ContainerInterface::class));
        $request = new Request(['include' => 'addresses,orders.items,wishlists']);
        $context = ['request' => $request];

        // Act
        $result = $resolver->parseIncludeParameter($context);

        // Assert
        $this->assertSame(['addresses', 'orders', 'orders.items', 'wishlists'], $result);
    }

    public function testGivenNoRequestInContextWhenParsingIncludeParameterThenReturnsEmptyArray(): void
    {
        // Arrange
        $resolver = new ApiPlatformRelationshipResolver([], $this->createMock(ContainerInterface::class), $this->createMock(ContainerInterface::class));
        $context = [];

        // Act
        $result = $resolver->parseIncludeParameter($context);

        // Assert
        $this->assertSame([], $result);
    }

    public function testGivenNoRelationshipConfigWhenResolvingRelationshipsThenReturnsEmptyArray(): void
    {
        // Arrange
        $resolver = new ApiPlatformRelationshipResolver([], $this->createMock(ContainerInterface::class), $this->createMock(ContainerInterface::class));
        $mainResources = [(object)['id' => 1]];

        // Act
        $result = $resolver->resolveRelationships('customers', $mainResources, ['addresses'], []);

        // Assert
        $this->assertSame([], $result);
    }

    public function testGivenValidRelationshipConfigWhenResolvingRelationshipsThenCallsProviderAndReturnsRelatedResources(): void
    {
        // Arrange
        $relatedResource = (object)['id' => 'addr-1', 'address1' => '123 Test St'];
        $mainResource = (object)['customerReference' => 'customer-001'];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('provide')
            ->with(
                $this->isInstanceOf(GetCollection::class),
                ['customerReference' => 'customer-001'],
                [],
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
            ->willReturn($provider);

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

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result = $resolver->resolveRelationships('customers', [$mainResource], ['addresses'], []);

        // Assert
        $this->assertArrayHasKey('addresses', $result);
        $this->assertSame([$relatedResource], $result['addresses']);
    }

    public function testGivenProviderNotFoundWhenResolvingRelationshipsThenReturnsEmptyArrayForThatRelationship(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001'];

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->expects($this->once())
            ->method('has')
            ->with('NonExistentProvider')
            ->willReturn(false);

        $relationships = [
            'customers.addresses' => [
                'relationship_name' => 'addresses',
                'target_resource_type' => 'addresses',
                'provider_service_id' => 'NonExistentProvider',
                'uri_variable_mappings' => [],
            ],
        ];

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result = $resolver->resolveRelationships('customers', [$mainResource], ['addresses'], []);

        // Assert
        $this->assertArrayHasKey('addresses', $result);
        $this->assertSame([], $result['addresses']);
    }

    public function testGivenMultipleMainResourcesWhenResolvingRelationshipsThenLoadsRelatedResourcesForEachMain(): void
    {
        // Arrange
        $relatedResource1 = (object)['id' => 'addr-1'];
        $relatedResource2 = (object)['id' => 'addr-2'];
        $mainResource1 = (object)['customerReference' => 'customer-001'];
        $mainResource2 = (object)['customerReference' => 'customer-002'];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->exactly(2))
            ->method('provide')
            ->willReturnOnConsecutiveCalls([$relatedResource1], [$relatedResource2]);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->method('has')->willReturn(true);
        $providerLocator->method('get')->willReturn($provider);

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

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result = $resolver->resolveRelationships('customers', [$mainResource1, $mainResource2], ['addresses'], []);

        // Assert
        $this->assertArrayHasKey('addresses', $result);
        $this->assertCount(2, $result['addresses']);
        $this->assertSame([$relatedResource1, $relatedResource2], $result['addresses']);
    }

    public function testGivenNullMappedPropertyWhenResolvingRelationshipsThenSkipsProviderCall(): void
    {
        // Arrange — parent's mapped property is null; the relationship must be skipped entirely
        // so the provider does not fall through to an unfiltered `GetCollection` and attach
        // every available item as a relationship.
        $mainResource = (object)['customerReference' => null];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->never())
            ->method('provide');

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->method('has')->willReturn(true);
        $providerLocator->method('get')->willReturn($provider);

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

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result = $resolver->resolveRelationships('customers', [$mainResource], ['addresses'], []);

        // Assert
        $this->assertArrayHasKey('addresses', $result);
        $this->assertSame([], $result['addresses']);
    }

    public function testGivenMixedNullAndValidMappingsWhenResolvingRelationshipsThenSkipsOnlyNullParents(): void
    {
        // Arrange — only the resource with a populated mapping should reach the provider.
        $relatedResource = (object)['id' => 'addr-1'];
        $mainResourceWithNull = (object)['customerReference' => null];
        $mainResourceWithValue = (object)['customerReference' => 'customer-001'];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('provide')
            ->with(
                $this->isInstanceOf(GetCollection::class),
                ['customerReference' => 'customer-001'],
                [],
            )
            ->willReturn([$relatedResource]);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->method('has')->willReturn(true);
        $providerLocator->method('get')->willReturn($provider);

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

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result = $resolver->resolveRelationships('customers', [$mainResourceWithNull, $mainResourceWithValue], ['addresses'], []);

        // Assert
        $this->assertArrayHasKey('addresses', $result);
        $this->assertSame([$relatedResource], $result['addresses']);
    }

    public function testGivenBatchLoadableProviderWithNullMappedPropertyWhenResolvingRelationshipsThenOmitsNullParentFromBatch(): void
    {
        // Arrange — only the resource with a populated mapping reaches the batch payload.
        $relatedResource = (object)['id' => 'addr-1'];
        $mainResourceWithNull = (object)['customerReference' => null];
        $mainResourceWithValue = (object)['customerReference' => 'customer-001'];

        $provider = $this->createMock(BatchLoadableProviderInterface::class);
        $provider->expects($this->once())
            ->method('provide')
            ->with(
                $this->isInstanceOf(GetCollection::class),
                [
                    BatchLoadableProviderInterface::BATCH_DATA_KEY => [
                        ['customerReference' => 'customer-001'],
                    ],
                ],
                [],
            )
            ->willReturn([[$relatedResource]]);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->method('has')->willReturn(true);
        $providerLocator->method('get')->willReturn($provider);

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

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result = $resolver->resolveRelationships('customers', [$mainResourceWithNull, $mainResourceWithValue], ['addresses'], []);

        // Assert
        $this->assertArrayHasKey('addresses', $result);
        $this->assertCount(1, $result['addresses']);
    }

    public function testGivenBatchLoadableProviderWhenResolvingRelationshipsThenCallsProvideWithBatchData(): void
    {
        // Arrange
        $relatedResource1 = (object)['id' => 'addr-1'];
        $relatedResource2 = (object)['id' => 'addr-2'];
        $mainResource1 = (object)['customerReference' => 'customer-001'];
        $mainResource2 = (object)['customerReference' => 'customer-002'];

        $provider = $this->createMock(BatchLoadableProviderInterface::class);
        $provider->expects($this->once())
            ->method('provide')
            ->with(
                $this->isInstanceOf(GetCollection::class),
                [
                    BatchLoadableProviderInterface::BATCH_DATA_KEY => [
                        ['customerReference' => 'customer-001'],
                        ['customerReference' => 'customer-002'],
                    ],
                ],
                [],
            )
            ->willReturn([[$relatedResource1], [$relatedResource2]]);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->method('has')->willReturn(true);
        $providerLocator->method('get')->willReturn($provider);

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

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result = $resolver->resolveRelationships('customers', [$mainResource1, $mainResource2], ['addresses'], []);

        // Assert
        $this->assertArrayHasKey('addresses', $result);
        $this->assertCount(2, $result['addresses']);
    }

    public function testGivenDuplicateIncludeRequestsWhenResolvingRelationshipsThenUsesCacheForSecondCall(): void
    {
        // Arrange
        $relatedResource = (object)['id' => 'addr-1'];
        $mainResource = (object)['customerReference' => 'customer-001'];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('provide')
            ->willReturn([$relatedResource]);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->method('has')->willReturn(true);
        $providerLocator->method('get')->willReturn($provider);

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

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result1 = $resolver->resolveRelationships('customers', [$mainResource], ['addresses'], []);
        $result2 = $resolver->resolveRelationships('customers', [$mainResource], ['addresses'], []);

        // Assert
        $this->assertSame($result1['addresses'], $result2['addresses']);
    }

    public function testGivenNestedIncludeWithMaxDepthWhenResolvingRelationshipsThenLimitsDepth(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001'];

        $providerLocator = $this->createMock(ContainerInterface::class);

        $relationships = [];

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $this->createMock(ContainerInterface::class));

        // Act
        $result = $resolver->resolveRelationships(
            'customers',
            [$mainResource],
            ['addresses.country.region.continent'],
            [],
        );

        // Assert
        $this->assertSame([], $result);
    }
}
