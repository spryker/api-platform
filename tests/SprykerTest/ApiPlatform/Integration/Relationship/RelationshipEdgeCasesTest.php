<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Relationship;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use Codeception\Test\Unit;
use Psr\Container\ContainerInterface;
use RuntimeException;
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
 * @group RelationshipEdgeCasesTest
 * Add your own group annotations below this line
 */
class RelationshipEdgeCasesTest extends Unit
{
    protected ApiIntegrationTester $tester;

    public function testGivenProviderReturnsNullWhenLoadingRelationshipThenHandledGracefully(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001', 'addresses' => null];

        $relatedProvider = $this->createMock(ProviderInterface::class);
        $relatedProvider->method('provide')
            ->willReturn(null);

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
        $this->assertSame([], $storedRelationships['addresses']);
    }

    public function testGivenProviderNotFoundWhenLoadingRelationshipThenHandledGracefully(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001', 'addresses' => null];

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->expects($this->once())
            ->method('has')
            ->with('NonExistentProvider')
            ->willReturn(false);
        $providerLocator->expects($this->never())->method('get');

        $relationships = [
            'customers.addresses' => [
                'relationship_name' => 'addresses',
                'target_resource_type' => 'addresses',
                'provider_service_id' => 'NonExistentProvider',
                'uri_variable_mappings' => ['customerReference' => 'customerReference'],
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
        $this->assertSame([], $storedRelationships['addresses']);
    }

    public function testGivenInnerProviderReturnsNullWhenProvidingThenReturnsNullWithoutLoadingRelationships(): void
    {
        // Arrange
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn(null);

        $providerLocator = $this->createMock(ContainerInterface::class);
        $providerLocator->expects($this->never())->method('has');

        $request = new Request(['include' => 'addresses']);
        $resolverLocator = $this->createMock(ContainerInterface::class);

        $resolver = new ApiPlatformRelationshipResolver([], $providerLocator, $resolverLocator, $this->createMock(ContainerInterface::class));
        $decorator = new RelationshipProvider($innerProvider, $resolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');
        $context = ['request' => $request];

        // Act
        $result = $decorator->provide($operation, [], $context);

        // Assert
        $this->assertNull($result);
    }

    public function testGivenNoRequestInContextWhenProvidingThenParsesIncludeAsEmpty(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001'];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($mainResource);

        $providerLocator = $this->createMock(ContainerInterface::class);

        $resolverLocator = $this->createMock(ContainerInterface::class);

        $resolver = new ApiPlatformRelationshipResolver([], $providerLocator, $resolverLocator, $this->createMock(ContainerInterface::class));
        $decorator = new RelationshipProvider($innerProvider, $resolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');
        $context = [];

        // Act
        $result = $decorator->provide($operation, [], $context);

        // Assert
        $this->assertSame($mainResource, $result);
    }

    public function testGivenNoCurrentRequestInStackWhenProvidingThenDoesNotStoreRelationships(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001'];
        $relatedResource = (object)['uuid' => 'addr-001'];

        $relatedProvider = $this->createMock(ProviderInterface::class);
        $relatedProvider->expects($this->once())
            ->method('provide')
            ->willReturn([$relatedResource]);

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
    }

    public function testGivenProviderThrowsExceptionWhenLoadingRelationshipThenExceptionBubbles(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001'];

        $relatedProvider = $this->createMock(ProviderInterface::class);
        $relatedProvider->expects($this->once())
            ->method('provide')
            ->willThrowException(new RuntimeException('Provider error'));

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
            ->willReturn($mainResource);

        $request = new Request(['include' => 'addresses']);
        $resolverLocator = $this->createMock(ContainerInterface::class);

        $resolver = new ApiPlatformRelationshipResolver($relationships, $providerLocator, $resolverLocator, $this->createMock(ContainerInterface::class));
        $decorator = new RelationshipProvider($innerProvider, $resolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');
        $context = ['request' => $request];

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider error');

        // Act
        $decorator->provide($operation, [], $context);
    }

    public function testGivenEmptyIncludeParameterWhenProvidingThenNoRelationshipsLoaded(): void
    {
        // Arrange
        $mainResource = (object)['customerReference' => 'customer-001'];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($mainResource);

        $providerLocator = $this->createMock(ContainerInterface::class);

        $request = new Request(['include' => '']);
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

    public function testGivenResourceWithoutPropertyWhenMappingUriVariablesThenSkipsMapping(): void
    {
        // Arrange
        $mainResource = (object)['name' => 'John Doe'];

        $relatedProvider = $this->createMock(ProviderInterface::class);
        $relatedProvider->expects($this->never())
            ->method('provide');

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
    }
}
