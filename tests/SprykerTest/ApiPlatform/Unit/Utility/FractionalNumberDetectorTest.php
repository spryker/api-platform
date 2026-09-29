<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Utility;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Utility\FractionalNumberDetector;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Utility
 * @group FractionalNumberDetectorTest
 * Add your own group annotations below this line
 */
class FractionalNumberDetectorTest extends Unit
{
    /**
     * @dataProvider provideFractionalNumbers
     */
    public function testGivenANumberWithAFractionalPartWhenDetectingThenItIsFractional(mixed $value): void
    {
        // Act
        $isFractional = FractionalNumberDetector::isFractional($value);

        // Assert
        $this->assertTrue($isFractional);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function provideFractionalNumbers(): array
    {
        return [
            'a float' => [1.5],
            'a negative float' => [-0.1],
            'a numeric string' => ['1.5'],
            'a numeric string in exponent notation' => ['1.5e-1'],
        ];
    }

    /**
     * @dataProvider provideValuesWithoutAFractionalPart
     */
    public function testGivenAValueWithoutAFractionalPartWhenDetectingThenItIsNotFractional(mixed $value): void
    {
        // Act
        $isFractional = FractionalNumberDetector::isFractional($value);

        // Assert
        $this->assertFalse($isFractional);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function provideValuesWithoutAFractionalPart(): array
    {
        return [
            'an integer' => [2],
            'an integral float' => [2.0],
            'an integer string' => ['2'],
            'a negative integer string' => ['-2'],
            'an integral float string' => ['2.0'],
            'an integral exponent string' => ['1e3'],
            'a non-numeric string' => ['abc'],
            'an empty string' => [''],
            'null' => [null],
            'a boolean' => [true],
        ];
    }
}
