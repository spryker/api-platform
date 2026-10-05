<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\RequestAttributePathExtractor;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group RequestAttributePathExtractorTest
 * Add your own group annotations below this line
 */
class RequestAttributePathExtractorTest extends Unit
{
    public function testGivenScalarAttributesWhenExtractingThenEachNonEmptyValueIsAPath(): void
    {
        // Act
        $paths = (new RequestAttributePathExtractor())->extract(['currency' => 'EUR', 'priceMode' => 'GROSS_MODE', 'isDefault' => false, 'quantity' => 0]);

        // Assert
        $this->assertSame(['currency', 'priceMode', 'isDefault', 'quantity'], $paths);
    }

    public function testGivenNullEmptyStringOrEmptyArrayWhenExtractingThenNoPathIsRecorded(): void
    {
        // Act
        $paths = (new RequestAttributePathExtractor())->extract(['store' => null, 'name' => '', 'items' => []]);

        // Assert
        $this->assertSame([], $paths);
    }

    public function testGivenANestedObjectWhenExtractingThenTheObjectAndItsChildrenArePaths(): void
    {
        // Act
        $paths = (new RequestAttributePathExtractor())->extract(['salesUnit' => ['id' => 1, 'amount' => '1.5', 'name' => null]]);

        // Assert
        $this->assertSame(['salesUnit', 'salesUnit.id', 'salesUnit.amount'], $paths);
    }

    public function testGivenAListOfObjectsWhenExtractingThenChildPathsUseTheListWildcard(): void
    {
        // Act
        $paths = (new RequestAttributePathExtractor())->extract([
        'shipments' => [
            ['items' => ['a'], 'idShipmentMethod' => 1],
            ['items' => ['b'], 'requestedDeliveryDate' => '2026-10-01'],
        ]]);

        // Assert
        $this->assertSame(['shipments', 'shipments[].items', 'shipments[].idShipmentMethod', 'shipments[].requestedDeliveryDate'], $paths);
    }
}
