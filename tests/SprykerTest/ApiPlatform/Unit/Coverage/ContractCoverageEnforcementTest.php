<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageDimension;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ContractCoverageEnforcementTest
 * Add your own group annotations below this line
 */
class ContractCoverageEnforcementTest extends Unit
{
    public function testGivenConfiguredValuesWhenBuildingEnforcementThenOnlyThoseDimensionsAreEnforced(): void
    {
        // Arrange
        $values = ['error-codes', 'includes', 'error-codes'];

        // Act
        $enforcement = ContractCoverageEnforcement::fromValues($values);

        // Assert
        $this->assertSame([ContractCoverageDimension::ERROR_CODES, ContractCoverageDimension::INCLUDES], $enforcement->enforcedDimensions);
        $this->assertTrue($enforcement->isEnforced(ContractCoverageDimension::INCLUDES));
        $this->assertFalse($enforcement->isEnforced(ContractCoverageDimension::REQUEST_ATTRIBUTES));
    }

    public function testGivenAnUnknownValueWhenBuildingEnforcementThenItThrows(): void
    {
        // Arrange
        $values = ['error-codes', 'error-code'];

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown contract-coverage dimension "error-code"');

        // Act
        ContractCoverageEnforcement::fromValues($values);
    }

    public function testGivenAllWhenCheckingAnyDimensionThenItIsEnforced(): void
    {
        // Arrange
        $enforcement = ContractCoverageEnforcement::fromValues([ContractCoverageEnforcement::ALL]);

        // Act
        $unenforced = array_filter(
            ContractCoverageDimension::cases(),
            static fn (ContractCoverageDimension $dimension): bool => !$enforcement->isEnforced($dimension),
        );

        // Assert
        $this->assertSame([], $unenforced);
    }

    public function testGivenAConfiguredSetWhenWideningItThenTheConfiguredDimensionsStayEnforced(): void
    {
        // Arrange
        $configured = ContractCoverageEnforcement::fromValues(['includes']);

        // Act
        $widened = $configured->withAdditional(ContractCoverageEnforcement::fromValues(['error-codes']));

        // Assert
        $this->assertTrue($widened->isEnforced(ContractCoverageDimension::INCLUDES));
        $this->assertTrue($widened->isEnforced(ContractCoverageDimension::ERROR_CODES));
        $this->assertFalse($widened->isEnforced(ContractCoverageDimension::OWNERSHIP_SCENARIOS));
    }
}
