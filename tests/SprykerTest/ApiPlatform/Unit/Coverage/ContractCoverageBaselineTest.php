<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Spryker\ApiPlatform\Contract\Coverage\BaselineEntry;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageBaseline;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension;
use Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage;
use Spryker\ApiPlatform\Contract\Coverage\IncludeRelationship;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ContractCoverageBaselineTest
 * Add your own group annotations below this line
 */
class ContractCoverageBaselineTest extends Unit
{
    protected const string DISPATCH_KEY_ORDERS = 'GET /orders';

    protected const string RELATIONSHIP_MERCHANTS = 'merchants';

    protected const string RELATIONSHIP_ORDER_ITEMS = 'order-items';

    protected const string REASON = 'The merchants include names a relationship resolver that does not exist, so it is never loaded.';

    protected const string NON_EMPTY_ARRAY_KEY = 'GET /orders/{orderReference}  items[].calculatedDiscounts';

    public function testGivenAnUncoveredItemTheBaselineNamesWhenApplyingThenItMovesFromTheGapsToTheBaselinedItems(): void
    {
        // Arrange
        $merchantsInclude = new IncludeRelationship(static::DISPATCH_KEY_ORDERS, static::RELATIONSHIP_MERCHANTS);
        $orderItemsInclude = new IncludeRelationship(static::DISPATCH_KEY_ORDERS, static::RELATIONSHIP_ORDER_ITEMS);
        $baseline = ContractCoverageBaseline::fromConfiguration([
            ContractCoverageDimension::INCLUDES->value => [$merchantsInclude->key() => static::REASON],
        ]);

        // Act
        $dimensionCoverage = $baseline->apply(ContractCoverageDimension::INCLUDES, new DimensionCoverage([], [$merchantsInclude, $orderItemsInclude]), true);

        // Assert
        $this->assertSame([$orderItemsInclude], $dimensionCoverage->uncovered);
        $this->assertEquals([new BaselineEntry($merchantsInclude->key(), static::REASON)], $dimensionCoverage->baselined);
        $this->assertSame([], $dimensionCoverage->baselineEntriesToRemove());
    }

    public function testGivenABaselinedItemThatAClaimCoversWhenApplyingThenTheEntryIsToBeRemoved(): void
    {
        // Arrange
        $merchantsInclude = new IncludeRelationship(static::DISPATCH_KEY_ORDERS, static::RELATIONSHIP_MERCHANTS);
        $baseline = ContractCoverageBaseline::fromConfiguration([
            ContractCoverageDimension::INCLUDES->value => [$merchantsInclude->key() => static::REASON],
        ]);

        // Act
        $dimensionCoverage = $baseline->apply(ContractCoverageDimension::INCLUDES, new DimensionCoverage([$merchantsInclude]), false);

        // Assert
        $this->assertEquals([new BaselineEntry($merchantsInclude->key(), static::REASON)], $dimensionCoverage->baselineEntriesNowCovered);
        $this->assertSame([], $dimensionCoverage->baselined);
    }

    public function testGivenARunOverTheWholeScopeWhenAnEntryNamesNoUncoveredItemThenTheEntryIsToBeRemoved(): void
    {
        // Arrange
        $baseline = ContractCoverageBaseline::fromConfiguration([
            ContractCoverageDimension::INCLUDES->value => ['GET /gone  include merchants' => static::REASON],
        ]);

        // Act
        $dimensionCoverage = $baseline->apply(ContractCoverageDimension::INCLUDES, new DimensionCoverage(), true);

        // Assert
        $this->assertEquals([new BaselineEntry('GET /gone  include merchants', static::REASON)], $dimensionCoverage->baselineEntriesNamingNoGap);
    }

    public function testGivenARunNarrowedToSomeResourcesWhenAnEntryNamesNoUncoveredItemThenItIsNotJudged(): void
    {
        // Arrange - the entry may name a gap of a resource the run did not select.
        $baseline = ContractCoverageBaseline::fromConfiguration([
            ContractCoverageDimension::INCLUDES->value => ['GET /elsewhere  include merchants' => static::REASON],
        ]);

        // Act
        $dimensionCoverage = $baseline->apply(ContractCoverageDimension::INCLUDES, new DimensionCoverage(), false);

        // Assert
        $this->assertSame([], $dimensionCoverage->baselineEntriesToRemove());
    }

    public function testGivenARuntimeOnlyDimensionWhenApplyingThenEveryEntryIsListedAndNoneIsJudged(): void
    {
        // Arrange
        $baseline = ContractCoverageBaseline::fromConfiguration([
            ContractCoverageDimension::NON_EMPTY_ARRAYS->value => [static::NON_EMPTY_ARRAY_KEY => 'Order items never carry their calculated discounts.'],
        ]);

        // Act
        $dimensionCoverage = $baseline->apply(ContractCoverageDimension::NON_EMPTY_ARRAYS, new DimensionCoverage(), true);

        // Assert
        $this->assertEquals([new BaselineEntry(static::NON_EMPTY_ARRAY_KEY, 'Order items never carry their calculated discounts.')], $dimensionCoverage->baselined);
        $this->assertSame([], $dimensionCoverage->baselineEntriesToRemove());
    }

    public function testGivenABaselinedKeyWhenAskingWhetherItIsBaselinedThenOnlyItsOwnDimensionAnswersYes(): void
    {
        // Arrange
        $baseline = ContractCoverageBaseline::fromConfiguration([
            ContractCoverageDimension::NON_EMPTY_ARRAYS->value => [static::NON_EMPTY_ARRAY_KEY => 'Order items never carry their calculated discounts.'],
        ]);

        // Act
        $isBaselinedForNonEmptyArrays = $baseline->isBaselined(ContractCoverageDimension::NON_EMPTY_ARRAYS, static::NON_EMPTY_ARRAY_KEY);
        $isBaselinedForIncludes = $baseline->isBaselined(ContractCoverageDimension::INCLUDES, static::NON_EMPTY_ARRAY_KEY);

        // Assert
        $this->assertTrue($isBaselinedForNonEmptyArrays);
        $this->assertFalse($isBaselinedForIncludes);
    }

    public function testGivenAnUnknownDimensionWhenReadingTheConfigurationThenItFailsNamingTheKnownOnes(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unknown dimension "operations"/');

        // Act
        ContractCoverageBaseline::fromConfiguration(['operations' => ['GET /orders' => static::REASON]]);
    }

    public function testGivenAnEntryWithoutAReasonWhenReadingTheConfigurationThenItFails(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/gives no reason/');

        // Act
        ContractCoverageBaseline::fromConfiguration([ContractCoverageDimension::INCLUDES->value => ['GET /orders  include merchants' => ' ']]);
    }
}
