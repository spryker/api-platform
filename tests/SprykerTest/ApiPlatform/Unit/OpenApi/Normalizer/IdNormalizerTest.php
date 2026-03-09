<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\Normalizer;

use ApiPlatform\Metadata\IdentifiersExtractorInterface;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\OpenApi\Normalizer\IdNormalizer;
use stdClass;
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
}
