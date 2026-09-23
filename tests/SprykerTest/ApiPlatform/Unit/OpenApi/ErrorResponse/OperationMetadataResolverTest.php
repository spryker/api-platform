<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Serializer\NormalizeOperationNameTrait;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\OperationMetadataResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;
use SprykerTest\ApiPlatform\Fixture\ErrorResponseFixtureResource;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group ErrorResponse
 * @group OperationMetadataResolverTest
 * Add your own group annotations below this line
 */
class OperationMetadataResolverTest extends Unit
{
    protected const string URI_TEMPLATE_ITEM = '/fixture-categories/{categoryKey}{._format}';

    protected const string URI_TEMPLATE_COLLECTION = '/fixture-categories{._format}';

    /**
     * @see \ApiPlatform\Metadata\Resource\Factory\OperationNameResourceMetadataCollectionFactory
     */
    protected const string OPERATION_NAME_ITEM = '_api_/fixture-categories/{categoryKey}{._format}_get';

    protected const string OPERATION_NAME_COLLECTION = '_api_/fixture-categories{._format}_get_collection';

    protected const string OPERATION_NAME_POST = '_api_/fixture-categories{._format}_post';

    protected const string OPERATION_NAME_ROUTE = 'fixture_categories_export';

    protected const string OPERATION_ID_ITEM = 'api_fixture-categories_categoryKey_get';

    protected const string OPERATION_ID_COLLECTION = 'api_fixture-categories_get_collection';

    protected const string OPERATION_ID_POST = 'api_fixture-categories_post';

    protected const string OPERATION_ID_DECLARED = 'getFixtureCategoryByKey';

    protected const string ROUTE_NAME = 'fixture_categories_export';

    protected ApiUnitTester $tester;

    public function testGivenAutoNamedOperationWhenResolvingByTheFactoryOperationIdThenTheOperationIsReturned(): void
    {
        // Arrange
        $resolver = $this->createResolver([
            new Get(name: static::OPERATION_NAME_ITEM, uriTemplate: static::URI_TEMPLATE_ITEM),
            new GetCollection(name: static::OPERATION_NAME_COLLECTION, uriTemplate: static::URI_TEMPLATE_COLLECTION),
        ]);

        // Act
        $itemOperation = $resolver->resolve(static::OPERATION_ID_ITEM);
        $collectionOperation = $resolver->resolve(static::OPERATION_ID_COLLECTION);

        // Assert
        $this->assertInstanceOf(Get::class, $itemOperation);
        $this->assertInstanceOf(GetCollection::class, $collectionOperation);
    }

    public function testGivenDeclaredOperationIdWhenResolvingThenTheDeclaredIdWinsOverTheOperationName(): void
    {
        // Arrange
        $resolver = $this->createResolver([
            new Get(
                name: static::OPERATION_NAME_ITEM,
                uriTemplate: static::URI_TEMPLATE_ITEM,
                openapi: new Operation(operationId: static::OPERATION_ID_DECLARED),
            ),
        ]);

        // Act
        $declaredOperation = $resolver->resolve(static::OPERATION_ID_DECLARED);
        $normalisedOperation = $resolver->resolve(static::OPERATION_ID_ITEM);

        // Assert
        $this->assertInstanceOf(Get::class, $declaredOperation);
        $this->assertNull($normalisedOperation);
    }

    public function testGivenOperationWithRouteNameOnlyWhenResolvingThenItIsIndexedLikeAnyDocumentedOperation(): void
    {
        // Arrange
        $resolver = $this->createResolver([
            new Get(name: static::OPERATION_NAME_ROUTE, routeName: static::ROUTE_NAME),
        ]);

        // Act
        $operation = $resolver->resolve(static::OPERATION_NAME_ROUTE);

        // Assert
        $this->assertInstanceOf(Get::class, $operation);
    }

    public function testGivenOperationHiddenFromOpenApiWhenResolvingThenItIsNotResolved(): void
    {
        // Arrange
        $resolver = $this->createResolver([
            new Post(name: static::OPERATION_NAME_POST, uriTemplate: static::URI_TEMPLATE_COLLECTION, openapi: false),
        ]);

        // Act
        $operation = $resolver->resolve(static::OPERATION_ID_POST);

        // Assert
        $this->assertNull($operation);
    }

    public function testGivenOperationWithoutUriTemplateAndRouteNameWhenResolvingThenItIsNotResolved(): void
    {
        // Arrange
        $resolver = $this->createResolver([
            new Get(name: static::OPERATION_NAME_ITEM),
        ]);

        // Act
        $operation = $resolver->resolve(static::OPERATION_ID_ITEM);

        // Assert
        $this->assertNull($operation);
    }

    public function testGivenUnknownOrMissingOperationIdWhenResolvingThenReturnsNull(): void
    {
        // Arrange
        $resolver = $this->createResolver([
            new Get(name: static::OPERATION_NAME_ITEM, uriTemplate: static::URI_TEMPLATE_ITEM),
        ]);

        // Act
        $unknownOperation = $resolver->resolve(static::OPERATION_ID_COLLECTION);
        $missingOperation = $resolver->resolve(null);

        // Assert
        $this->assertNull($unknownOperation);
        $this->assertNull($missingOperation);
    }

    public function testGivenOperationNameWithEveryNormalisedCharacterWhenResolvingByTheVendorNormalisationThenTheOperationIsReturned(): void
    {
        // Arrange
        $vendorNormaliser = new class {
            use NormalizeOperationNameTrait;

            public function normalize(string $operationName): string
            {
                return $this->normalizeOperationName($operationName);
            }
        };
        $resolver = $this->createResolver([
            new Get(name: static::OPERATION_NAME_ITEM, uriTemplate: static::URI_TEMPLATE_ITEM),
        ]);

        // Act
        $operation = $resolver->resolve($vendorNormaliser->normalize(static::OPERATION_NAME_ITEM));

        // Assert
        $this->assertInstanceOf(Get::class, $operation);
    }

    /**
     * @param array<\ApiPlatform\Metadata\HttpOperation> $operations
     */
    protected function createResolver(array $operations): OperationMetadataResolver
    {
        $resourceNameCollectionFactoryMock = $this->createMock(ResourceNameCollectionFactoryInterface::class);
        $resourceNameCollectionFactoryMock->method('create')
            ->willReturn(new ResourceNameCollection([ErrorResponseFixtureResource::class]));

        $resourceMetadataCollectionFactoryMock = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataCollectionFactoryMock->method('create')
            ->willReturn(new ResourceMetadataCollection(ErrorResponseFixtureResource::class, [new ApiResource(operations: $operations)]));

        return new OperationMetadataResolver($resourceNameCollectionFactoryMock, $resourceMetadataCollectionFactoryMock);
    }
}
