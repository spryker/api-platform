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

    public function testGivenEmptyValuesAreRejectedWhenAnEmptyArrayIsRecordedThenThePathStaysUnasserted(): void
    {
        // Arrange
        $recorder = new ResponseAttributeRecorder();
        $recorder->recordValue('discounts', []);

        // Act
        $missing = $recorder->verify(['discounts'], true);

        // Assert
        $this->assertSame(['discounts'], $missing);
        $this->assertSame(['discounts'], $recorder->emptyAssertedPaths());
    }

    public function testGivenEmptyValuesAreRejectedWhenANonEmptyArrayIsRecordedThenThePathIsAsserted(): void
    {
        // Arrange
        $recorder = new ResponseAttributeRecorder();
        $recorder->recordValue('discounts', [['code' => 'SUMMER']]);

        // Act
        $missing = $recorder->verify(['discounts'], true);

        // Assert
        $this->assertSame([], $missing);
    }

    public function testGivenEmptyValuesAreAcceptedWhenAnEmptyArrayIsRecordedThenThePathIsAsserted(): void
    {
        // Arrange
        $recorder = new ResponseAttributeRecorder();
        $recorder->recordValue('discounts', []);

        // Act
        $missing = $recorder->verify(['discounts']);

        // Assert
        $this->assertSame([], $missing);
    }

    public function testGivenAnArrayAssertedAsEmptyWhenVerifyingItsElementPathsThenTheyAreNeitherMissingNorAsserted(): void
    {
        // Arrange — an empty cart carries no discount, so the element fields are the job of the test
        // whose fixture has one.
        $recorder = new ResponseAttributeRecorder();
        $recorder->recordValue('discounts', []);
        $recorder->record('name');

        // Act
        $missing = $recorder->verify(['name', 'discounts[].code', 'discounts[].amount', 'thresholds[].type']);

        // Assert
        $this->assertSame(['thresholds[].type'], $missing);
    }

    public function testGivenEmptyValuesAreRejectedWhenAnArrayIsAssertedOnlyAsEmptyThenItsElementPathsStayMissing(): void
    {
        // Arrange
        $recorder = new ResponseAttributeRecorder();
        $recorder->recordValue('discounts', []);

        // Act
        $missing = $recorder->verify(['discounts[].code', 'discounts[].amount'], true);

        // Assert
        $this->assertSame(['discounts[].code', 'discounts[].amount'], $missing);
        $this->assertSame(['discounts'], $recorder->emptyAssertedPaths());
    }

    public function testGivenAnEmptyArrayAndAnAssertedElementOfItWhenVerifyingThenTheOtherElementPathsAreDemanded(): void
    {
        // Arrange
        $recorder = new ResponseAttributeRecorder();
        $recorder->recordValue('[0].discounts', []);
        $recorder->record('[1].discounts[0].code');

        // Act
        $missing = $recorder->verify(['discounts[].code', 'discounts[].amount']);

        // Assert
        $this->assertSame(['discounts[].amount'], $missing);
    }
}
