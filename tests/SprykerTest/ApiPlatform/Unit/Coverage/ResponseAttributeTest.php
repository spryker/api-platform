<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ResponseAttributeTest
 * Add your own group annotations below this line
 */
class ResponseAttributeTest extends Unit
{
    public function testGivenAnOperationAndPathWhenBuildingTheKeyThenItJoinsBothWithTwoSpaces(): void
    {
        // Arrange
        $responseAttribute = new ResponseAttribute('GET /agent-customer-search', 'customers[].firstName');

        // Act
        $key = $responseAttribute->key();

        // Assert
        $this->assertSame('GET /agent-customer-search  customers[].firstName', $key);
    }
}
