<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\BaselineEntry;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;
use Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ContractCoverageResultBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ContractCoverageResultTest
 * Add your own group annotations below this line
 */
class ContractCoverageResultTest extends Unit
{
    public function testGivenUncoveredItemsOfANonEnforcedDimensionWhenJudgingThenTheResultIsSuccessful(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withDimensionCoverage(
                ContractCoverageDimension::ERROR_CODES,
                new DimensionCoverage([], [new ApiOperation('DELETE', '/carts/{cartUuid}', 422)], [new ApiOperation('DELETE', '/gone/{cartUuid}', 422)]),
            )
            ->withEnforcement(ContractCoverageEnforcement::fromValues(['includes']))
            ->build();

        // Act
        $failureReasons = $result->failureReasons();

        // Assert
        $this->assertSame([], $failureReasons);
        $this->assertTrue($result->isSuccessful());
    }

    public function testGivenAnEnforcedDimensionWhoseOnlyGapIsBaselinedWhenJudgingThenTheResultIsSuccessful(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withDimensionCoverage(
                ContractCoverageDimension::INCLUDES,
                new DimensionCoverage([], [], [], [new BaselineEntry('GET /orders  include merchants', 'The merchants include is never loaded.')]),
            )
            ->withEnforcement(ContractCoverageEnforcement::fromValues(['includes']))
            ->build();

        // Act
        $failureReasons = $result->failureReasons();

        // Assert
        $this->assertSame([], $failureReasons);
    }

    public function testGivenABaselineEntryToRemoveInADimensionThatIsNotEnforcedWhenJudgingThenTheResultFails(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withDimensionCoverage(
                ContractCoverageDimension::INCLUDES,
                new DimensionCoverage([], [], [], [], [new BaselineEntry('GET /orders  include merchants', 'The merchants include is never loaded.')]),
            )
            ->build();

        // Act
        $failureReasons = $result->failureReasons();

        // Assert
        $this->assertSame(['1 include baseline entry(ies) to remove'], $failureReasons);
    }

    public function testGivenUncoveredItemsOfAnEnforcedDimensionWhenJudgingThenTheResultNamesThemAsAFailureReason(): void
    {
        // Arrange
        $result = ContractCoverageResultBuilder::create()
            ->withDimensionCoverage(
                ContractCoverageDimension::ERROR_CODES,
                new DimensionCoverage([], [new ApiOperation('DELETE', '/carts/{cartUuid}', 422)], [new ApiOperation('DELETE', '/gone/{cartUuid}', 422)]),
            )
            ->withEnforcement(ContractCoverageEnforcement::fromValues(['error-codes']))
            ->build();

        // Act
        $failureReasons = $result->failureReasons();

        // Assert
        $this->assertSame(['1 uncovered error code(s)', '1 stale error code claim(s)'], $failureReasons);
        $this->assertFalse($result->isSuccessful());
    }
}
