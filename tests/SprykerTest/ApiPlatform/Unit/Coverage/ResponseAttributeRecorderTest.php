<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeRecorder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ResponseAttributeRecorderTest
 * Add your own group annotations below this line
 */
class ResponseAttributeRecorderTest extends Unit
{
    public function testGivenRecordedIndexedPathsWhenVerifyingThenWildcardExpectationsAreSatisfied(): void
    {
        // Arrange
        $recorder = new ResponseAttributeRecorder();
        $recorder->record('customers[0].firstName');
        $recorder->record('[1].name');

        // Act
        $missing = $recorder->verify(['customers[].firstName', 'name', 'customers[].email']);

        // Assert
        $this->assertSame(['customers[].email'], $missing);
    }
}
