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

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ApiOperationTest
 * Add your own group annotations below this line
 */
class ApiOperationTest extends Unit
{
    public function testGivenACodeWhenBuildingTheKeyThenItIsAppendedAfterTheStatus(): void
    {
        // Arrange
        $operation = new ApiOperation('delete', '/carts/{cartUuid}/cart-codes/{code}', 422, code: '3301');

        // Act
        $key = $operation->key();

        // Assert
        $this->assertSame('DELETE /carts/{cartUuid}/cart-codes/{code} 422 code 3301', $key);
    }

    public function testGivenACodeAndScenarioWhenBuildingTheKeyThenBothFacetsAreAppendedInOrder(): void
    {
        // Arrange
        $operation = new ApiOperation('GET', '/customers/{customerReference}/carts', 403, code: '802', scenario: Scenario::FOREIGN_OWNER);

        // Act
        $key = $operation->key();

        // Assert
        $this->assertSame('GET /customers/{customerReference}/carts 403 code 802 scenario foreign-owner', $key);
    }

    public function testGivenAScenarioDeclarationWhenListingCoverageKeysThenTheStatuslessScenarioKeyIsIncluded(): void
    {
        // Arrange
        $operation = new ApiOperation('GET', '/a', 403, scenario: Scenario::FOREIGN_OWNER);

        // Act
        $coverageKeys = $operation->coverageKeys();

        // Assert
        $this->assertSame(['GET /a 403 scenario foreign-owner', 'GET /a 403', 'GET /a scenario foreign-owner'], $coverageKeys);
    }

    public function testGivenACodeDeclarationWhenListingCoverageKeysThenItsStatusKeyIsIncluded(): void
    {
        // Arrange
        $operation = new ApiOperation('DELETE', '/carts/{cartUuid}', 422, code: '3301');

        // Act
        $coverageKeys = $operation->coverageKeys();

        // Assert
        $this->assertSame(['DELETE /carts/{cartUuid} 422 code 3301', 'DELETE /carts/{cartUuid} 422'], $coverageKeys);
    }

    public function testGivenASuccessDeclarationWhenListingCoverageKeysThenOnlyItsOwnKeyIsReturned(): void
    {
        // Arrange
        $operation = new ApiOperation('GET', '/carts');

        // Act
        $coverageKeys = $operation->coverageKeys();

        // Assert
        $this->assertSame(['GET /carts'], $coverageKeys);
    }
}
