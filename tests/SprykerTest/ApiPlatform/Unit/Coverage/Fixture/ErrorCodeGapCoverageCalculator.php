<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\CollectedAnnotations;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension;
use Spryker\ApiPlatform\Contract\Coverage\CoverageCalculator;
use Spryker\ApiPlatform\Contract\Coverage\CoverageReport;
use Spryker\ApiPlatform\Contract\Coverage\DimensionCoverage;
use Spryker\ApiPlatform\Contract\Coverage\TruthSet;

/**
 * Adds one uncovered error-code item to whatever the real calculation finds, so a command test can
 * observe how enforcement decides the verdict without a schema that declares codes.
 */
class ErrorCodeGapCoverageCalculator extends CoverageCalculator
{
    /**
     * @param array<string, array<\Spryker\ApiPlatform\Contract\Coverage\ErrorMappingEntry>> $errorMappingEntries
     */
    public function calculate(
        TruthSet $enforcedTruth,
        TruthSet $existenceTruth,
        CollectedAnnotations $annotations,
        array $errorMappingEntries = []
    ): CoverageReport {
        $report = parent::calculate($enforcedTruth, $existenceTruth, $annotations, $errorMappingEntries);

        return new CoverageReport(
            $report->coveredOperations,
            $report->uncoveredOperations,
            $report->staleOperations,
            $report->coveredValidations,
            $report->uncoveredValidations,
            $report->staleValidations,
            $report->coveredResponseAttributes,
            $report->uncoveredResponseAttributes,
            [ContractCoverageDimension::ERROR_CODES->value => new DimensionCoverage([], [new ApiOperation('GET', '/declared', 404)])],
        );
    }
}
