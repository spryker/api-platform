<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\Normalizer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IdentifiersExtractorInterface;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Property\PropertyNameCollection;
use Codeception\Test\Unit;
use RuntimeException;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\ApiPlatform\OpenApi\Normalizer\IdNormalizer;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group Normalizer
 * @group IdNormalizerTest
 * Add your own group annotations below this line
 */
class IdNormalizerTest extends Unit
{
    protected const string URI_TEMPLATE_WITHOUT_URI_VARIABLES = '/principal';

    protected const string URI_TEMPLATE_WITH_URI_VARIABLES = '/wishlists/{uuid}';

    protected const string URI_VARIABLE_UUID = 'uuid';

    public function testGivenGenIdFalseWhenNormalizingThenSetsContextIriToIdentifier(): void
    {
        // Arrange
        $object = new stdClass();
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willReturn(['accessToken' => 'jwt-token-value']);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->with(
                $object,
                'jsonapi',
                $this->callback(function (array $context): bool {
                    return isset($context['iri']) && $context['iri'] === 'jwt-token-value';
                }),
            )
            ->willReturn([
                'data' => [
                    'type' => 'tokens',
                    'id' => 'jwt-token-value',
                ],
            ]);

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', [
            'gen_id' => false,
        ]);

        // Assert
        $this->assertEquals('jwt-token-value', $result['data']['id']);
    }

    public function testGivenGenIdTrueWhenNormalizingThenDoesNotPreSetIri(): void
    {
        // Arrange
        $object = new stdClass();
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willReturn(['id' => 'some-id']);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->with(
                $object,
                'jsonapi',
                $this->callback(function (array $context): bool {
                    return !isset($context['iri']);
                }),
            )
            ->willReturn([
                'data' => [
                    'type' => 'customers',
                    'id' => '/customers/some-id',
                ],
            ]);

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', []);

        // Assert
        $this->assertEquals('some-id', $result['data']['id']);
    }

    public function testGivenGenIdMissingWhenNormalizingThenDoesNotPreSetIri(): void
    {
        // Arrange
        $object = new stdClass();
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willReturn(['id' => 'entity-id']);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->with(
                $object,
                'jsonapi',
                $this->callback(function (array $context): bool {
                    return !isset($context['iri']);
                }),
            )
            ->willReturn([
                'data' => [
                    'type' => 'items',
                    'id' => '/items/entity-id',
                ],
            ]);

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', []);

        // Assert
        $this->assertEquals('entity-id', $result['data']['id']);
    }

    public function testGivenIdentifierExtractionFailsWhenNormalizingThenFallsBackToTheResourceUuid(): void
    {
        // Arrange
        $object = new class {
            public ?string $uuid = 'order-item-uuid';
        };
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willThrowException(new RuntimeException('Not able to retrieve identifiers.'));

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn([
            'data' => [
                'type' => 'order-items',
                'id' => '/orders/DE--1/order-items/order-item-uuid',
            ],
        ]);

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', []);

        // Assert
        $this->assertEquals('order-item-uuid', $result['data']['id']);
    }

    public function testGivenAResourceWithoutUriVariablesWhenNormalizingThenTheIdIsTheDeclaredIdentifierProperty(): void
    {
        // Arrange
        $object = new class {
            public ?string $consent = 'consent-uuid';

            public ?string $store = 'DE';
        };
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')->willReturn([]);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn([
            'data' => [
                'type' => 'agentic-commerce-principals',
                'id' => null,
            ],
        ]);

        $normalizer = new IdNormalizer(
            $identifiersExtractor,
            $this->createPropertyNameCollectionFactory(['store', 'consent']),
            $this->createPropertyMetadataFactory(['consent']),
        );
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', ['gen_id' => false, 'operation' => $this->createOperationWithoutUriVariables()]);

        // Assert
        $this->assertSame('consent-uuid', $result['data']['id']);
    }

    public function testGivenAResourceWithoutUriVariablesAndWithoutADeclaredIdentifierWhenNormalizingThenTheIdStaysNull(): void
    {
        // Arrange
        $object = new class {
            public ?string $store = 'DE';
        };
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')->willReturn([]);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn([
            'data' => [
                'type' => 'stores',
                'id' => null,
            ],
        ]);

        $normalizer = new IdNormalizer(
            $identifiersExtractor,
            $this->createPropertyNameCollectionFactory(['store']),
            $this->createPropertyMetadataFactory([]),
        );
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', ['gen_id' => false, 'operation' => $this->createOperationWithoutUriVariables()]);

        // Assert
        $this->assertNull($result['data']['id']);
    }

    public function testGivenAPostOperationWithoutUriVariablesAndADeclaredIdentifierWhenNormalizingThenTheIdStaysNull(): void
    {
        // Arrange
        $object = new class {
            public ?string $accessToken = 'jwt';
        };
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')->willReturn([]);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->with(
                $object,
                'jsonapi',
                $this->callback(fn (array $context): bool => ($context['iri'] ?? null) !== 'jwt'),
            )
            ->willReturn([
                'data' => [
                    'type' => 'access-tokens',
                    'id' => null,
                ],
            ]);

        $normalizer = new IdNormalizer(
            $identifiersExtractor,
            $this->createPropertyNameCollectionFactory(['accessToken']),
            $this->createPropertyMetadataFactory(['accessToken']),
        );
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', ['gen_id' => false, 'operation' => new Post(uriTemplate: '/access-tokens', uriVariables: [])]);

        // Assert
        $this->assertNull($result['data']['id']);
    }

    public function testGivenAResourceWithUriVariablesThatReturnsANullIdWithoutAnIdentifierPropertyWhenNormalizingThenTheIdStaysNull(): void
    {
        // Arrange
        $object = new class {
            public ?string $name = 'Default wishlist';
        };
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willThrowException(new RuntimeException('Not able to retrieve identifiers.'));

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn([
            'data' => [
                'type' => 'wishlists',
                'id' => null,
            ],
        ]);

        $normalizer = new IdNormalizer(
            $identifiersExtractor,
            $this->createPropertyNameCollectionFactory(['name']),
            $this->createPropertyMetadataFactory([]),
        );
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', ['operation' => $this->createOperationWithUriVariables()]);

        // Assert
        $this->assertNull($result['data']['id']);
    }

    public function testGivenAResourceWithUriVariablesThatReturnsANullIdWhenItHasAUuidPropertyThenTheIdStaysNull(): void
    {
        // Arrange
        $object = new class {
            public ?string $uuid = 'wishlist-uuid';
        };
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willThrowException(new RuntimeException('Not able to retrieve identifiers.'));

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn([
            'data' => [
                'type' => 'wishlists',
                'id' => null,
            ],
        ]);

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', ['operation' => $this->createOperationWithUriVariables()]);

        // Assert
        $this->assertNull($result['data']['id']);
    }

    public function testGivenANullIdWithoutAnOperationInTheContextWhenNormalizingThenTheIdStaysNull(): void
    {
        // Arrange
        $object = new class {
            public ?string $uuid = 'wishlist-uuid';
        };
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')->willReturn([]);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn([
            'data' => [
                'type' => 'wishlists',
                'id' => null,
            ],
        ]);

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', []);

        // Assert
        $this->assertNull($result['data']['id']);
    }

    public function testGivenASingletonResourceWhenNormalizingThenTheIdStaysNull(): void
    {
        // Arrange
        $object = new stdClass();
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willReturn(['id' => 'checkout-data']);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn([
            'data' => [
                'type' => 'checkout-data',
                'id' => '/checkout-data/checkout-data',
            ],
        ]);

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($object, 'jsonapi', []);

        // Assert
        $this->assertNull($result['data']['id']);
    }

    public function testGivenAnItemWithNoIdentifierWhenTheIriCannotBeBuiltThenItReportsTheBackFillAsTheRemedy(): void
    {
        // Arrange
        $object = new stdClass();
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willThrowException(new RuntimeException('No identifier value found.'));

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->willThrowException(new InvalidArgumentException('Unable to generate an IRI for the item of type "Foo"'));

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Act
        try {
            $normalizer->normalize($object, 'jsonapi');
        } catch (GlueApiException $glueApiException) {
            // Assert
            $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $glueApiException->getStatusCode());
            $this->assertSame('012', $glueApiException->getErrorCode());
            $this->assertStringContainsString(stdClass::class, $glueApiException->getMessage());
            $this->assertStringContainsString('uuid:generate', $glueApiException->getMessage());

            return;
        }

        $this->fail('Expected a GlueApiException naming the missing identifier.');
    }

    public function testGivenAnItemThatHasAnIdentifierWhenTheIriCannotBeBuiltThenTheOriginalErrorSurvives(): void
    {
        // Arrange
        $object = new stdClass();
        $identifiersExtractor = $this->createMock(IdentifiersExtractorInterface::class);
        $identifiersExtractor->method('getIdentifiersFromItem')
            ->willReturn(['uuid' => 'a-real-uuid']);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')
            ->willThrowException(new InvalidArgumentException('Unable to generate an IRI for the item of type "Foo"'));

        $normalizer = new IdNormalizer($identifiersExtractor);
        $normalizer->setNormalizer($innerNormalizer);

        // Expect
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to generate an IRI for the item of type "Foo"');

        // Act
        $normalizer->normalize($object, 'jsonapi');
    }

    protected function createOperationWithoutUriVariables(): Get
    {
        return new Get(uriTemplate: static::URI_TEMPLATE_WITHOUT_URI_VARIABLES, uriVariables: []);
    }

    protected function createOperationWithUriVariables(): Get
    {
        return new Get(uriTemplate: static::URI_TEMPLATE_WITH_URI_VARIABLES, uriVariables: [static::URI_VARIABLE_UUID => new Link(fromClass: stdClass::class, identifiers: [static::URI_VARIABLE_UUID])]);
    }

    /**
     * @param list<string> $propertyNames
     */
    protected function createPropertyNameCollectionFactory(array $propertyNames): PropertyNameCollectionFactoryInterface
    {
        $propertyNameCollectionFactory = $this->createMock(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactory->method('create')->willReturn(new PropertyNameCollection($propertyNames));

        return $propertyNameCollectionFactory;
    }

    /**
     * @param list<string> $identifierPropertyNames
     */
    protected function createPropertyMetadataFactory(array $identifierPropertyNames): PropertyMetadataFactoryInterface
    {
        $propertyMetadataFactory = $this->createMock(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactory->method('create')->willReturnCallback(
            fn (string $resourceClass, string $propertyName): ApiProperty => new ApiProperty(identifier: in_array($propertyName, $identifierPropertyNames, true)),
        );

        return $propertyMetadataFactory;
    }
}
