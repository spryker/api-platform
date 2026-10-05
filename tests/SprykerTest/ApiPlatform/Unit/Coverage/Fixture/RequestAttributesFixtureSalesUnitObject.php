<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\ApiProperty;

/**
 * A nested value object of {@see RequestAttributesFixtureResource}: two writable children and a
 * read-only one.
 */
class RequestAttributesFixtureSalesUnitObject
{
    public ?string $id = null;

    public ?string $amount = null;

    #[ApiProperty(writable: false)]
    public ?string $name = null;
}
