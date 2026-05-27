<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Provider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use Codeception\Test\Unit;
use Psr\Container\ContainerInterface;
use Spryker\ApiPlatform\Provider\RelationshipProvider;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolverInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Provider
 * @group RelationshipProviderDecoratorTest
 * Add your own group annotations below this line
 */
class RelationshipProviderDecoratorTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenInnerProviderReturnsNullWhenProvidingThenReturnsNull(): void
    {
        // Arrange
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn(null);

        $relationshipResolver = $this->createMock(ApiPlatformRelationshipResolverInterface::class);
        $relationshipResolver->expects($this->never())->method('parseIncludeParameter');

        $decorator = new RelationshipProvider($innerProvider, $relationshipResolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');

        // Act
        $result = $decorator->provide($operation, [], []);

        // Assert
        $this->assertNull($result);
    }

    public function testGivenNoIncludesRequestedWhenProvidingThenReturnsResultWithoutResolvingRelationships(): void
    {
        // Arrange
        $resource = (object)['id' => 1];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($resource);

        $relationshipResolver = $this->createMock(ApiPlatformRelationshipResolverInterface::class);
        $relationshipResolver->expects($this->once())
            ->method('parseIncludeParameter')
            ->willReturn([]);
        $relationshipResolver->expects($this->never())->method('resolveRelationships');

        $decorator = new RelationshipProvider($innerProvider, $relationshipResolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');

        // Act
        $result = $decorator->provide($operation, [], []);

        // Assert
        $this->assertSame($resource, $result);
    }

    public function testGivenIncludesRequestedWhenProvidingThenResolvesRelationshipsAndStoresInRequest(): void
    {
        // Arrange
        $resource = (object)['id' => 1, 'addresses' => null];
        $relationships = ['addresses' => [(object)['id' => 'addr-1']]];

        $request = new Request();

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($resource);

        $relationshipResolver = $this->createMock(ApiPlatformRelationshipResolverInterface::class);
        $relationshipResolver->expects($this->once())
            ->method('parseIncludeParameter')
            ->willReturn(['addresses']);
        $relationshipResolver->expects($this->once())
            ->method('resolveRelationships')
            ->with('customers', [$resource], ['addresses'], $this->callback(function ($context) use ($request) {
                return isset($context['request']) && $context['request'] === $request;
            }))
            ->willReturn($relationships);

        $decorator = new RelationshipProvider($innerProvider, $relationshipResolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');

        // Act
        $result = $decorator->provide($operation, [], ['request' => $request]);

        // Assert
        $this->assertSame($resource, $result);

        $storedRelationships = $request->attributes->get('_spryker_resolved_relationships', []);
        $this->assertArrayHasKey('addresses', $storedRelationships);
        $this->assertSame($relationships['addresses'], $storedRelationships['addresses']);
    }

    public function testGivenCollectionResultWhenProvidingThenResolvesRelationshipsForAllResources(): void
    {
        // Arrange
        $resource1 = (object)['id' => 1];
        $resource2 = (object)['id' => 2];
        $resources = [$resource1, $resource2];
        $relationships = ['addresses' => [(object)['id' => 'addr-1']]];

        $request = new Request();

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($resources);

        $relationshipResolver = $this->createMock(ApiPlatformRelationshipResolverInterface::class);
        $relationshipResolver->expects($this->once())
            ->method('parseIncludeParameter')
            ->willReturn(['addresses']);
        $relationshipResolver->expects($this->once())
            ->method('resolveRelationships')
            ->with('customers', $resources, ['addresses'], $this->callback(function ($context) use ($request) {
                return isset($context['request']) && $context['request'] === $request;
            }))
            ->willReturn($relationships);

        $decorator = new RelationshipProvider($innerProvider, $relationshipResolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');

        // Act
        $result = $decorator->provide($operation, [], ['request' => $request]);

        // Assert
        $this->assertSame($resources, $result);

        // For collections, relationships are not assigned to individual items in the array
        // The implementation only assigns relationships when result is a single object
        $this->assertFalse(property_exists($resource1, 'addresses'));
        $this->assertFalse(property_exists($resource2, 'addresses'));
    }

    public function testGivenNoCurrentRequestWhenProvidingThenDoesNotStoreRelationships(): void
    {
        // Arrange
        $resource = (object)['id' => 1];
        $relationships = ['addresses' => [(object)['id' => 'addr-1']]];

        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('provide')
            ->willReturn($resource);

        $relationshipResolver = $this->createMock(ApiPlatformRelationshipResolverInterface::class);
        $relationshipResolver->expects($this->once())
            ->method('parseIncludeParameter')
            ->willReturn(['addresses']);
        $relationshipResolver->expects($this->once())
            ->method('resolveRelationships')
            ->willReturn($relationships);

        $decorator = new RelationshipProvider($innerProvider, $relationshipResolver, $this->createMock(ContainerInterface::class));

        $operation = new Get(shortName: 'customers');

        // Act
        $result = $decorator->provide($operation, [], []);

        // Assert
        $this->assertSame($resource, $result);
    }
}
