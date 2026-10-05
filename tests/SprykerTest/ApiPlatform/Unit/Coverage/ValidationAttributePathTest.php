<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ValidationAttributePath;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ValidationAttributePathTest
 * Add your own group annotations below this line
 */
class ValidationAttributePathTest extends Unit
{
    public function testGivenBracketNotationWithIndexesWhenNormalizingThenItBecomesADotPathWithoutIndexes(): void
    {
        // Act
        $normalized = [
            ValidationAttributePath::normalize('productConfigurationInstance[prices][0][currency][code]'),
            ValidationAttributePath::normalize('items[0].sku'),
        ];

        // Assert
        $this->assertSame(['productConfigurationInstance.prices.currency.code', 'items.sku'], $normalized);
    }

    public function testGivenDotNotationWhenNormalizingThenItIsUnchanged(): void
    {
        // Act
        $normalized = ValidationAttributePath::normalize('salesUnit.amount');

        // Assert
        $this->assertSame('salesUnit.amount', $normalized);
    }

    public function testGivenAMapKeyInBracketsWhenMatchingTheMapAttributeThenItMatches(): void
    {
        // Act
        $matches = ValidationAttributePath::matches('unitPriceMap[any-group-key]', 'unitPriceMap');

        // Assert
        $this->assertTrue($matches);
    }

    public function testGivenACollectionFieldInBracketsWhenMatchingTheNestedAttributeThenItMatches(): void
    {
        // Act
        $matches = ValidationAttributePath::matches('productConfigurationInstance[prices][0][currency][code]', 'productConfigurationInstance.prices.currency.code');

        // Assert
        $this->assertTrue($matches);
    }

    public function testGivenADottedPropertyWhenMatchingAnAttributeWithoutItThenItDoesNotMatch(): void
    {
        // Act
        $matches = ValidationAttributePath::matches('salesUnit.amount', 'salesUnit');

        // Assert
        $this->assertFalse($matches);
    }
}
