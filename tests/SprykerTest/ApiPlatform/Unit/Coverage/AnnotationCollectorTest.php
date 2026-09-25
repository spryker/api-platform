<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use LogicException;
use Spryker\ApiPlatform\Contract\Coverage\AnnotationCollector;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\AnnotatedCoverageFixture;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SharedOperationFirstDeclarerFixture;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SharedOperationSecondDeclarerFixture;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\UnboundResponseAttributeMarkerFixture;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\UnboundValidationCoverageFixture;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group AnnotationCollectorTest
 * Add your own group annotations below this line
 */
class AnnotationCollectorTest extends Unit
{
    public function testGivenAnnotatedTestMethodsWhenCollectingThenOnlyTestMethodAttributesAreGathered(): void
    {
        // Arrange
        $collector = new AnnotationCollector();

        // Act
        $collected = $collector->collect([AnnotatedCoverageFixture::class]);

        // Assert — only public test* methods are read, a status keys its own error entry, and a
        // validation declaration binds to the operation on the same method.
        $this->assertSame(
            ['GET /wishlists', 'POST /wishlists', 'GET /wishlists/{uuid} 404', 'GET /fixtures', 'POST /fixtures'],
            array_map(static fn ($operation) => $operation->key(), $collected->declaredOperations),
        );
        $this->assertSame(
            ['wishlists.name.NotBlank on POST /wishlists'],
            array_map(static fn ($validation) => $validation->key(), $collected->declaredValidations),
        );
    }

    public function testGivenAValidationDeclarationWithoutAnOperationOnTheMethodWhenCollectingThenItFailsLoudly(): void
    {
        // Arrange
        $collector = new AnnotationCollector();

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('needs a #[CoversApiOperation] on the same method');

        // Act
        $collector->collect([UnboundValidationCoverageFixture::class]);
    }

    public function testGivenASingleMethodWhenCollectingItsOperationsThenOnlyThatMethodsDeclarationsAreReturned(): void
    {
        // Act
        $operations = AnnotationCollector::operationsForMethod(
            AnnotatedCoverageFixture::class,
            'testGivenABlankNameWhenPostThenItFails',
        );

        // Assert
        $this->assertSame(
            ['POST /wishlists'],
            array_map(static fn ($operation) => $operation->key(), $operations),
        );
    }

    public function testGivenAnUnannotatedMethodWhenCollectingItsOperationsThenTheResultIsEmpty(): void
    {
        // Act
        $operations = AnnotationCollector::operationsForMethod(
            AnnotatedCoverageFixture::class,
            'testGivenAnUnannotatedMethodWhenCollectingThenItContributesNothing',
        );

        // Assert
        $this->assertSame([], $operations);
    }

    public function testGivenAMarkedMethodWhenCollectingThenItsOperationIsResponseAttributeCovered(): void
    {
        // Act
        $collected = (new AnnotationCollector())->collect([AnnotatedCoverageFixture::class]);

        // Assert
        $this->assertContains('POST /fixtures', $collected->responseAttributeCoveredOperations);
        $this->assertNotContains('GET /fixtures', $collected->responseAttributeCoveredOperations);
    }

    public function testGivenASuccessDeclarationWhenCollectingThenTheDeclaringMethodIsRecorded(): void
    {
        // Act
        $collected = (new AnnotationCollector())->collect([AnnotatedCoverageFixture::class]);

        // Assert
        $this->assertContains(
            AnnotatedCoverageFixture::class . '::testGivenFixturesWhenGetCollectionThenNamesAreReturned',
            $collected->operationDeclarers['GET /fixtures'],
        );
    }

    public function testGivenTwoClassesDeclaringOneOperationWhenCollectingThenBothDeclarersLandUnderItsDispatchKey(): void
    {
        // Arrange
        $collector = new AnnotationCollector();

        // Act
        $collected = $collector->collect([
            SharedOperationFirstDeclarerFixture::class,
            SharedOperationSecondDeclarerFixture::class,
        ]);

        // Assert — the declarers of one dispatch key merge across classes rather than the last
        // class overwriting the rest.
        $this->assertSame(
            [
                SharedOperationFirstDeclarerFixture::class . '::testGivenSharedDeclarersWhenGetCollectionThenTheyAreReturned',
                SharedOperationSecondDeclarerFixture::class . '::testGivenSharedDeclarersWhenGetCollectionThenTheEnvelopeIsCorrect',
            ],
            $collected->operationDeclarers['GET /shared-declarers'],
        );
    }

    public function testGivenAMarkedMethodWithoutASuccessOperationWhenCollectingThenItFails(): void
    {
        // Assert
        $this->expectException(LogicException::class);

        // Act
        (new AnnotationCollector())->collect([UnboundResponseAttributeMarkerFixture::class]);
    }
}
