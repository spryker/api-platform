<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\CollectedAnnotations;
use Spryker\ApiPlatform\Contract\Coverage\CoverageCalculator;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute;
use Spryker\ApiPlatform\Contract\Coverage\TruthSet;
use Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group CoverageCalculatorTest
 * Add your own group annotations below this line
 */
class CoverageCalculatorTest extends Unit
{
    public function testGivenAPartiallyAnnotatedTruthSetWhenCalculatingThenCoveredUncoveredAndStaleAreSeparated(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [new ApiOperation('GET', '/a'), new ApiOperation('POST', '/a')],
            [new ApiOperation('GET', '/a/{id}')],
            [new ValidationConstraint('a', 'name', 'NotBlank', 'POST', '/a')],
        );
        $annotations = new CollectedAnnotations(
            [new ApiOperation('GET', '/a'), new ApiOperation('DELETE', '/gone')],
            [new ValidationConstraint('a', 'name', 'NotBlank', 'POST', '/a'), new ValidationConstraint('a', 'ghost', 'NotBlank', 'POST', '/a')],
        );

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $this->assertSame(['GET /a'], $this->operationKeys($report->coveredOperations));
        $this->assertSame(['POST /a'], $this->operationKeys($report->uncoveredOperations));
        $this->assertSame(['DELETE /gone'], $this->operationKeys($report->staleOperations));
        $this->assertSame(['a.name.NotBlank on POST /a'], $this->validationKeys($report->coveredValidations));
        $this->assertSame([], $this->validationKeys($report->uncoveredValidations));
        $this->assertSame(['a.ghost.NotBlank on POST /a'], $this->validationKeys($report->staleValidations));
        $this->assertTrue($report->hasFailures());
    }

    public function testGivenADeclarationTargetingANonServableOperationWhenCalculatingThenItIsNeitherCoveredNorStale(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [new ApiOperation('GET', '/a')],
            [new ApiOperation('GET', '/a/{id}')],
            [],
        );
        $annotations = new CollectedAnnotations(
            [new ApiOperation('GET', '/a'), new ApiOperation('GET', '/a/{id}')],
            [],
        );

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert — the non-servable declaration is a real operation, so not stale; and it is not
        // part of the servable must-cover set, so it does not appear as covered or uncovered.
        $this->assertSame(['GET /a'], $this->operationKeys($report->coveredOperations));
        $this->assertSame([], $this->operationKeys($report->uncoveredOperations));
        $this->assertSame([], $this->operationKeys($report->staleOperations));
        $this->assertFalse($report->hasFailures());
    }

    public function testGivenADeclarationTargetingAnInternalOperationWhenCalculatingThenItIsNotStale(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [new ApiOperation('GET', '/a')],
            [],
            [],
            [],
            [],
            [new ApiOperation('GET', '/a/{id}')],
        );
        $annotations = new CollectedAnnotations(
            [new ApiOperation('GET', '/a'), new ApiOperation('GET', '/a/{id}')],
            [],
        );

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert — an internal operation exists in the schema, so a claim on it must not be reported
        // as pointing at nothing; it is still outside the must-cover set.
        $this->assertSame(['GET /a'], $this->operationKeys($report->coveredOperations));
        $this->assertSame([], $this->operationKeys($report->staleOperations));
        $this->assertFalse($report->hasFailures());
    }

    public function testGivenFullCoverageAndNoStaleClaimsWhenCalculatingThenThereAreNoFailures(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [new ApiOperation('GET', '/a')],
            [],
            [new ValidationConstraint('a', 'name', 'NotBlank', 'POST', '/a')],
        );
        $annotations = new CollectedAnnotations(
            [new ApiOperation('GET', '/a')],
            [new ValidationConstraint('a', 'name', 'NotBlank', 'POST', '/a')],
        );

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $this->assertFalse($report->hasFailures());
    }

    public function testGivenADeclarationForAnOutOfScopeButRealOperationWhenCalculatingThenItIsNeitherUncoveredNorStale(): void
    {
        // Arrange
        $enforcedTruth = new TruthSet([new ApiOperation('GET', '/a')], [], []);
        $existenceTruth = new TruthSet([new ApiOperation('GET', '/a'), new ApiOperation('GET', '/b')], [], []);
        $annotations = new CollectedAnnotations(
            [new ApiOperation('GET', '/a'), new ApiOperation('GET', '/b')],
            [],
        );

        // Act
        $report = (new CoverageCalculator())->calculate($enforcedTruth, $existenceTruth, $annotations);

        // Assert — /b exists in the schema but is outside the enforced scope, so it is neither an
        // uncovered gap nor a stale claim.
        $this->assertSame(['GET /a'], $this->operationKeys($report->coveredOperations));
        $this->assertSame([], $this->operationKeys($report->uncoveredOperations));
        $this->assertSame([], $this->operationKeys($report->staleOperations));
        $this->assertFalse($report->hasFailures());
    }

    public function testGivenTruthAndMarkersWhenCalculatingThenResponseAttributesAreBucketedPerOperation(): void
    {
        // Arrange
        $enforcedTruth = new TruthSet([], [], [], responseAttributes: [
            new ResponseAttribute('GET /fixtures', 'name'),
            new ResponseAttribute('GET /fixtures', 'lines[].sku'),
            new ResponseAttribute('POST /fixtures', 'name'),
        ]);
        $annotations = new CollectedAnnotations([], [], ['POST /fixtures']);

        // Act
        $report = (new CoverageCalculator())->calculate($enforcedTruth, new TruthSet([], [], [], [], []), $annotations);

        // Assert — the marker is claimed per operation, so every attribute of the marked operation
        // is covered at once and every attribute of an unmarked one is a gap.
        $this->assertSame(['POST /fixtures  name'], $this->responseAttributeKeys($report->coveredResponseAttributes));
        $this->assertSame(
            ['GET /fixtures  name', 'GET /fixtures  lines[].sku'],
            $this->responseAttributeKeys($report->uncoveredResponseAttributes),
        );
        $this->assertTrue($report->hasFailures());
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $operations
     *
     * @return array<string>
     */
    protected function operationKeys(array $operations): array
    {
        return array_map(static fn ($operation) => $operation->key(), $operations);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $validations
     *
     * @return array<string>
     */
    protected function validationKeys(array $validations): array
    {
        return array_map(static fn ($validation) => $validation->key(), $validations);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $responseAttributes
     *
     * @return array<string>
     */
    protected function responseAttributeKeys(array $responseAttributes): array
    {
        return array_map(static fn ($responseAttribute) => $responseAttribute->key(), $responseAttributes);
    }
}
