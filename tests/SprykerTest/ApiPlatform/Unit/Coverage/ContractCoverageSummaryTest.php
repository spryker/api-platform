<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageSummary;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute;
use Spryker\ApiPlatform\Contract\Coverage\SchemaDefect;
use Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ContractCoverageResultBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ContractCoverageSummaryTest
 * Add your own group annotations below this line
 */
class ContractCoverageSummaryTest extends Unit
{
    public function testGivenCoveredAndUncoveredEntriesWhenSummarisingThenTheTotalsExcludeStaleClaims(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withUncoveredOperations(new ApiOperation('GET', '/orders'))
            ->withStaleOperations(new ApiOperation('GET', '/gone'))
            ->withCoveredValidations(new ValidationConstraint('wishlists', 'name', 'NotBlank', 'POST', '/wishlists'))
            ->withStaleValidations(new ValidationConstraint('wishlists', 'gone', 'NotBlank', 'POST', '/wishlists'))
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert
        $this->assertSame(1, $summary->coveredOperationCount);
        $this->assertSame(1, $summary->uncoveredOperationCount);
        $this->assertSame(1, $summary->staleOperationCount);
        $this->assertSame(2, $summary->operationTotal());
        $this->assertSame(1, $summary->validationTotal());
    }

    public function testGivenNonServableOperationsWhenSummarisingThenTheyAreCountedOutsideTheOperationTotal(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withNonServableOperations(new ApiOperation('GET', '/wishlists/{wishlistUuid}/wishlist-items/{uuid}'))
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert
        $this->assertSame(1, $summary->nonServableOperationCount);
        $this->assertSame(1, $summary->operationTotal());
    }

    public function testGivenInternalOperationsWhenSummarisingThenTheyAreCountedApartFromTheNonServableWarning(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withInternalOperations(new ApiOperation('GET', '/wishlists/{wishlistUuid}/wishlist-items/{uuid}'))
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert
        $this->assertSame(1, $summary->internalOperationCount);
        $this->assertSame(0, $summary->nonServableOperationCount);
        $this->assertSame(1, $summary->operationTotal());
        $this->assertSame([], $summary->warningSections);
        $this->assertSame(
            ['GET /wishlists/{wishlistUuid}/wishlist-items/{uuid}'],
            $summary->coveredSections['Internal operations (IRI anchors, not reachable and not published)'],
        );
    }

    public function testGivenGapsInEverySectionWhenSummarisingThenTheSectionsAreLabelledAndOrderedForReading(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withUncoveredOperations(new ApiOperation('GET', '/orders'))
            ->withStaleOperations(new ApiOperation('GET', '/gone'))
            ->withUncoveredValidations(new ValidationConstraint('orders', 'sku', 'NotBlank', 'POST', '/orders'))
            ->withStaleValidations(new ValidationConstraint('orders', 'gone', 'NotBlank', 'POST', '/orders'))
            ->withUncoveredResponseAttributes(new ResponseAttribute('GET /orders', 'items[].sku'))
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert
        $this->assertSame(
            [
                'Uncovered operations',
                'Stale operation claims',
                'Uncovered validation rules',
                'Stale validation claims',
                'Uncovered response attributes',
            ],
            array_keys($summary->gapSections),
        );
        $this->assertSame(['GET /orders'], $summary->gapSections['Uncovered operations']);
    }

    public function testGivenAnEmptySectionWhenSummarisingThenItIsOmittedEntirely(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withUncoveredOperations(new ApiOperation('GET', '/orders'))
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert
        $this->assertSame(['Uncovered operations'], array_keys($summary->gapSections));
        $this->assertSame([], $summary->coveredSections);
        $this->assertFalse($summary->hasCollapsedEntries());
    }

    public function testGivenUnsortedEntriesWhenSummarisingThenEachSectionIsSortedByKey(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withUncoveredOperations(
                new ApiOperation('GET', '/orders/{orderReference}', 404),
                new ApiOperation('GET', '/orders'),
                new ApiOperation('GET', '/orders/{orderReference}'),
            )
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert
        $this->assertSame(
            ['GET /orders', 'GET /orders/{orderReference}', 'GET /orders/{orderReference} 404'],
            $summary->gapSections['Uncovered operations'],
        );
    }

    public function testGivenCoveredAndNonServableEntriesWhenSummarisingThenTheyFormTheCollapsibleSections(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredOperations(new ApiOperation('GET', '/wishlists'))
            ->withNonServableOperations(new ApiOperation('GET', '/wishlists/{wishlistUuid}/wishlist-items/{uuid}'))
            ->withCoveredValidations(new ValidationConstraint('wishlists', 'name', 'NotBlank', 'POST', '/wishlists'))
            ->withCoveredResponseAttributes(new ResponseAttribute('GET /wishlists', 'name'))
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert
        $this->assertSame(
            ['Covered operations', 'Covered validation rules', 'Covered response attributes'],
            array_keys($summary->coveredSections),
        );
        $this->assertSame(
            ['Non-servable operations (no provider — review the resource)'],
            array_keys($summary->warningSections),
        );
        $this->assertTrue($summary->hasCollapsedEntries());
    }

    public function testGivenResponseAttributesWhenSummarisingThenTheyAreCountedAndTheGapsJoinTheGapSections(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withCoveredResponseAttributes(new ResponseAttribute('POST /wishlists', 'name'))
            ->withUncoveredResponseAttributes(
                new ResponseAttribute('GET /wishlists', 'name'),
                new ResponseAttribute('GET /wishlists', 'items[].sku'),
            )
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert — the dimension knows no stale bucket, so every derived attribute is in the total.
        $this->assertSame(1, $summary->coveredResponseAttributeCount);
        $this->assertSame(2, $summary->uncoveredResponseAttributeCount);
        $this->assertSame(3, $summary->responseAttributeTotal());
        $this->assertSame(
            ['GET /wishlists  items[].sku', 'GET /wishlists  name'],
            $summary->gapSections['Uncovered response attributes'],
        );
        $this->assertSame(
            ['GET /wishlists  items[].sku', 'GET /wishlists  name'],
            array_map(
                static fn (ResponseAttribute $responseAttribute): string => $responseAttribute->key(),
                $summary->uncoveredResponseAttributes,
            ),
        );
    }

    public function testGivenSchemaDefectsWhenSummarisingThenTheyAreCountedAndSortedByResourceAndOperation(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withSchemaDefects(
                new SchemaDefect(new ApiOperation('GET', '/stores'), 'stores', ['stores.resource.yml']),
                new SchemaDefect(new ApiOperation('PATCH', '/customer-password/{ref}'), 'customer-password', ['cp.resource.yml']),
            )
            ->build();

        // Act
        $summary = ContractCoverageSummary::fromResult($result);

        // Assert
        $this->assertSame(2, $summary->schemaDefectCount);
        $this->assertSame(
            ['customer-password PATCH /customer-password/{ref}', 'stores GET /stores'],
            array_map(static fn ($defect): string => $defect->key(), $summary->schemaDefects),
        );
    }
}
