<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\Normalizer;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\IdentifiersExtractorInterface;
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
}
