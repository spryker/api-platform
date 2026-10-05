<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Replay\Fixture;

use ApiPlatform\Metadata\ApiProperty;

/**
 * The nested value object of {@see ReplayFixtureResource}, whose children carry their own examples.
 */
class ReplayFixtureSalesUnitObject
{
    #[ApiProperty(openapiContext: ['example' => 7])]
    public ?int $id = null;

    #[ApiProperty(openapiContext: ['example' => '1.5'])]
    public ?string $amount = null;
}
