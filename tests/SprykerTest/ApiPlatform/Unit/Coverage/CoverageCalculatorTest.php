<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Attribute\Scenario;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\CollectedAnnotations;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension;
use Spryker\ApiPlatform\Contract\Coverage\CoverageCalculator;
use Spryker\ApiPlatform\Contract\Coverage\CoverageItem;
use Spryker\ApiPlatform\Contract\Coverage\CoverageReport;
use Spryker\ApiPlatform\Contract\Coverage\ErrorMappingEntry;
use Spryker\ApiPlatform\Contract\Coverage\IncludeRelationship;
use Spryker\ApiPlatform\Contract\Coverage\ReplayedResource;
use Spryker\ApiPlatform\Contract\Coverage\RequestAttribute;
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
        $this->assertSame(['GET /a'], $this->coverageItemKeys($report->coveredOperations));
        $this->assertSame(['POST /a'], $this->coverageItemKeys($report->uncoveredOperations));
        $this->assertSame(['DELETE /gone'], $this->coverageItemKeys($report->staleOperations));
        $this->assertSame(['a.name.NotBlank on POST /a'], $this->validationKeys($report->coveredValidations));
        $this->assertSame([], $this->validationKeys($report->uncoveredValidations));
        $this->assertSame(['a.ghost.NotBlank on POST /a'], $this->validationKeys($report->staleValidations));
        $this->assertTrue($this->hasGaps($report));
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
        $this->assertSame(['GET /a'], $this->coverageItemKeys($report->coveredOperations));
        $this->assertSame([], $this->coverageItemKeys($report->uncoveredOperations));
        $this->assertSame([], $this->coverageItemKeys($report->staleOperations));
        $this->assertFalse($this->hasGaps($report));
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
        $this->assertSame(['GET /a'], $this->coverageItemKeys($report->coveredOperations));
        $this->assertSame([], $this->coverageItemKeys($report->staleOperations));
        $this->assertFalse($this->hasGaps($report));
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
        $this->assertFalse($this->hasGaps($report));
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
        $this->assertSame(['GET /a'], $this->coverageItemKeys($report->coveredOperations));
        $this->assertSame([], $this->coverageItemKeys($report->uncoveredOperations));
        $this->assertSame([], $this->coverageItemKeys($report->staleOperations));
        $this->assertFalse($this->hasGaps($report));
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
        $this->assertTrue($this->hasGaps($report));
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\CoverageItem> $coverageItems
     *
     * @return array<string>
     */
    protected function coverageItemKeys(array $coverageItems): array
    {
        return array_map(static fn (CoverageItem $coverageItem): string => $coverageItem->key(), $coverageItems);
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

    public function testGivenADeclarationWithACodeWhenCalculatingThenBothTheCodeItemAndItsStatusItemAreCovered(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [new ApiOperation('DELETE', '/a', 422)],
            [],
            [],
            errorCodeOperations: [new ApiOperation('DELETE', '/a', 422, code: '1'), new ApiOperation('DELETE', '/a', 422, code: '2')],
        );
        $annotations = new CollectedAnnotations([new ApiOperation('DELETE', '/a', 422, code: '1')], []);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $errorCodes = $report->dimensionCoverage(ContractCoverageDimension::ERROR_CODES->value);
        $this->assertSame(['DELETE /a 422'], $this->coverageItemKeys($report->coveredOperations));
        $this->assertSame([], $this->coverageItemKeys($report->staleOperations));
        $this->assertSame(['DELETE /a 422 code 1'], $this->coverageItemKeys($errorCodes->covered));
        $this->assertSame(['DELETE /a 422 code 2'], $this->coverageItemKeys($errorCodes->uncovered));
    }

    public function testGivenAStatusDeclarationWithoutCodeWhenCalculatingThenTheCodeItemsStayUncovered(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [new ApiOperation('DELETE', '/a', 422)],
            [],
            [],
            errorCodeOperations: [new ApiOperation('DELETE', '/a', 422, code: '1')],
        );
        $annotations = new CollectedAnnotations([new ApiOperation('DELETE', '/a', 422)], []);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $this->assertSame(['DELETE /a 422'], $this->coverageItemKeys($report->coveredOperations));
        $this->assertSame(['DELETE /a 422 code 1'], $this->coverageItemKeys($report->dimensionCoverage(ContractCoverageDimension::ERROR_CODES->value)->uncovered));
    }

    public function testGivenADeclaredCodeTheSchemaDoesNotDeclareWhenCalculatingThenItIsAStaleErrorCodeClaim(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [new ApiOperation('DELETE', '/a', 422)],
            [],
            [],
            errorCodeOperations: [new ApiOperation('DELETE', '/a', 422, code: '1')],
        );
        $annotations = new CollectedAnnotations([new ApiOperation('DELETE', '/a', 422, code: '9')], []);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $this->assertSame([], $this->coverageItemKeys($report->staleOperations));
        $this->assertSame(['DELETE /a 422 code 9'], $this->coverageItemKeys($report->dimensionCoverage(ContractCoverageDimension::ERROR_CODES->value)->stale));
    }

    public function testGivenAScenarioDeclarationWhenCalculatingThenBothTheScenarioItemAndItsStatusItemAreCovered(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [new ApiOperation('GET', '/a', 403)],
            [],
            [],
            ownershipScenarioOperations: [new ApiOperation('GET', '/a', scenario: Scenario::FOREIGN_OWNER)],
        );
        $annotations = new CollectedAnnotations([new ApiOperation('GET', '/a', 403, scenario: Scenario::FOREIGN_OWNER)], []);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $this->assertSame(['GET /a 403'], $this->coverageItemKeys($report->coveredOperations));
        $this->assertSame(['GET /a scenario foreign-owner'], $this->coverageItemKeys($report->dimensionCoverage(ContractCoverageDimension::OWNERSHIP_SCENARIOS->value)->covered));
    }

    public function testGivenAScenarioDeclarationOnAnOperationWithoutOwnershipWhenCalculatingThenItIsStale(): void
    {
        // Arrange
        $truthSet = new TruthSet([new ApiOperation('GET', '/a', 403)], [], []);
        $annotations = new CollectedAnnotations([new ApiOperation('GET', '/a', 403, scenario: Scenario::FOREIGN_OWNER)], []);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $this->assertSame([], $this->coverageItemKeys($report->staleOperations));
        $this->assertSame(['GET /a scenario foreign-owner'], $this->coverageItemKeys($report->dimensionCoverage(ContractCoverageDimension::OWNERSHIP_SCENARIOS->value)->stale));
    }

    public function testGivenAMappedCodeDeclaredOnAnOperationOfTheRegisteringResourceWhenCalculatingThenTheEntryIsCovered(): void
    {
        // Arrange
        $truthSet = new TruthSet([], [], [], errorMappingRegistrations: [
            'Config::mapping' => ['resources' => ['carts'], 'notAnswered' => [], 'declaredErrorCodes' => [422 => ['118']]],
        ]);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, new CollectedAnnotations([], []), [
            'Config::mapping' => [new ErrorMappingEntry('Config::mapping', 'cart.locked', '118', 422)],
        ]);

        // Assert
        $this->assertSame(['Config::mapping  cart.locked  422 code 118'], $this->coverageItemKeys($report->dimensionCoverage(ContractCoverageDimension::ERROR_MAPPINGS->value)->covered));
    }

    public function testGivenAMappedCodeTheRegistrationDoesNotDeclareWhenCalculatingThenTheEntryIsUncovered(): void
    {
        // Arrange
        $truthSet = new TruthSet([], [], [], errorMappingRegistrations: [
            'Config::mapping' => ['resources' => ['carts'], 'notAnswered' => [], 'declaredErrorCodes' => [422 => ['101']]],
        ]);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, new CollectedAnnotations([], []), [
            'Config::mapping' => [new ErrorMappingEntry('Config::mapping', 'cart.locked', '118', 422)],
        ]);

        // Assert
        $this->assertSame(['Config::mapping  cart.locked  422 code 118'], $this->coverageItemKeys($report->dimensionCoverage(ContractCoverageDimension::ERROR_MAPPINGS->value)->uncovered));
    }

    public function testGivenAMappedCodeDeclaredOnlyByARegisteringResourceOutsideTheRunWhenCalculatingThenTheEntryIsCovered(): void
    {
        // Arrange
        $enforcedTruth = new TruthSet([], [], [], errorMappingRegistrations: [
            'WishlistsRestApiConfig::getErrorMapping' => ['resources' => ['wishlist-items'], 'notAnswered' => [], 'declaredErrorCodes' => []],
        ]);
        $existenceTruth = new TruthSet([], [], [], errorMappingRegistrations: [
            'WishlistsRestApiConfig::getErrorMapping' => ['resources' => ['wishlist-items', 'wishlists'], 'notAnswered' => [], 'declaredErrorCodes' => [404 => ['206']]],
        ]);

        // Act
        $report = (new CoverageCalculator())->calculate($enforcedTruth, $existenceTruth, new CollectedAnnotations([], []), [
            'WishlistsRestApiConfig::getErrorMapping' => [new ErrorMappingEntry('WishlistsRestApiConfig::getErrorMapping', 'wishlist.not-found', '206', 404)],
        ]);

        // Assert
        $errorMappings = $report->dimensionCoverage(ContractCoverageDimension::ERROR_MAPPINGS->value);
        $this->assertSame(['WishlistsRestApiConfig::getErrorMapping  wishlist.not-found  404 code 206'], $this->coverageItemKeys($errorMappings->covered));
        $this->assertSame([], $errorMappings->uncovered);
    }

    public function testGivenANotAnsweredCodeWhenCalculatingThenTheEntryIsCovered(): void
    {
        // Arrange
        $truthSet = new TruthSet([], [], [], errorMappingRegistrations: [
            'Config::mapping' => ['resources' => ['carts'], 'notAnswered' => ['118' => 'Only the legacy merge answers it.'], 'declaredErrorCodes' => []],
        ]);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, new CollectedAnnotations([], []), [
            'Config::mapping' => [new ErrorMappingEntry('Config::mapping', 'cart.locked', '118', 422)],
        ]);

        // Assert
        $errorMappings = $report->dimensionCoverage(ContractCoverageDimension::ERROR_MAPPINGS->value);
        $this->assertCount(1, $errorMappings->covered);
        $this->assertSame([], $errorMappings->uncovered);
    }

    public function testGivenANotAnsweredCodeTheMappingDoesNotContainWhenCalculatingThenItIsStale(): void
    {
        // Arrange
        $truthSet = new TruthSet([], [], [], errorMappingRegistrations: [
            'Config::mapping' => ['resources' => ['carts'], 'notAnswered' => ['999' => 'Renamed away.'], 'declaredErrorCodes' => []],
        ]);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, new CollectedAnnotations([], []), [
            'Config::mapping' => [new ErrorMappingEntry('Config::mapping', 'cart.locked', '118', 422)],
        ]);

        // Assert
        $this->assertSame(['Config::mapping  notAnswered  code 999'], $this->coverageItemKeys($report->dimensionCoverage(ContractCoverageDimension::ERROR_MAPPINGS->value)->stale));
    }

    public function testGivenTwoTestsClaimingDisjointPathsWhenCalculatingThenTheirUnionIsCovered(): void
    {
        // Arrange
        $truthSet = new TruthSet([], [], [], requestAttributes: [
            new RequestAttribute('PATCH /carts/{cartUuid}', 'currency'),
            new RequestAttribute('PATCH /carts/{cartUuid}', 'priceMode'),
            new RequestAttribute('PATCH /carts/{cartUuid}', 'store'),
        ]);
        $annotations = new CollectedAnnotations([], [], requestAttributeClaims: ['PATCH /carts/{cartUuid}' => ['currency', 'priceMode']]);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $requestAttributes = $report->dimensionCoverage(ContractCoverageDimension::REQUEST_ATTRIBUTES->value);
        $this->assertSame(['PATCH /carts/{cartUuid}  currency', 'PATCH /carts/{cartUuid}  priceMode'], $this->coverageItemKeys($requestAttributes->covered));
        $this->assertSame(['PATCH /carts/{cartUuid}  store'], $this->coverageItemKeys($requestAttributes->uncovered));
    }

    public function testGivenAClaimedPathTheSchemaDoesNotDeclareWhenCalculatingThenItIsAStaleRequestAttributeClaim(): void
    {
        // Arrange
        $truthSet = new TruthSet([], [], [], requestAttributes: [new RequestAttribute('PATCH /carts/{cartUuid}', 'currency')]);
        $annotations = new CollectedAnnotations([], [], requestAttributeClaims: ['PATCH /carts/{cartUuid}' => ['currency', 'colour']]);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $this->assertSame(['PATCH /carts/{cartUuid}  colour'], $this->coverageItemKeys($report->dimensionCoverage(ContractCoverageDimension::REQUEST_ATTRIBUTES->value)->stale));
    }

    public function testGivenAnIncludeClaimOnAWriteOperationWhenCalculatingThenItIsNeitherStaleNorEnforced(): void
    {
        // Arrange
        $truthSet = new TruthSet(
            [],
            [],
            [],
            includeRelationships: [new IncludeRelationship('GET /carts/{cartUuid}', 'items')],
            writeIncludeRelationships: [new IncludeRelationship('POST /carts', 'items')],
        );
        $annotations = new CollectedAnnotations([], [], includeClaims: ['POST /carts' => ['items'], 'GET /carts/{cartUuid}' => ['colours']]);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $includes = $report->dimensionCoverage(ContractCoverageDimension::INCLUDES->value);
        $this->assertSame(['GET /carts/{cartUuid}  include items'], $this->coverageItemKeys($includes->uncovered));
        $this->assertSame(['GET /carts/{cartUuid}  include colours'], $this->coverageItemKeys($includes->stale));
    }

    public function testGivenAResourceNoReplayTestNamesWhenCalculatingThenItsOperationsAreUncoveredReplays(): void
    {
        // Arrange
        $truthSet = new TruthSet([], [], [], replayableResources: [new ReplayedResource('carts'), new ReplayedResource('wishlists')]);
        $annotations = new CollectedAnnotations([], [], replayedResources: ['wishlists', 'gone']);

        // Act
        $report = (new CoverageCalculator())->calculate($truthSet, $truthSet, $annotations);

        // Assert
        $replay = $report->dimensionCoverage(ContractCoverageDimension::OPENAPI_EXAMPLE_REPLAY->value);
        $this->assertSame(['wishlists'], $this->coverageItemKeys($replay->covered));
        $this->assertSame(['carts'], $this->coverageItemKeys($replay->uncovered));
        $this->assertSame(['gone'], $this->coverageItemKeys($replay->stale));
    }

    protected function hasGaps(CoverageReport $report): bool
    {
        return $report->uncoveredOperations !== []
            || $report->staleOperations !== []
            || $report->uncoveredValidations !== []
            || $report->staleValidations !== []
            || $report->uncoveredResponseAttributes !== [];
    }
}
