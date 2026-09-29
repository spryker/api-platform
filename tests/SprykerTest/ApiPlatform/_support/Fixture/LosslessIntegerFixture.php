<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

/**
 * Shaped like a generated resource: typed public properties, the scalar ones with a fluent setter.
 */
class LosslessIntegerFixture
{
    public ?int $quantity = null;

    public ?int $position = null;

    public ?float $price = null;

    public int|string|null $reference = null;

    public ?LosslessIntegerValueObjectFixture $salesUnit = null;

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;

        return $this;
    }
}
