<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(shortName: 'fixture-categories', provider: ErrorResponseFixtureProvider::class)]
class ErrorResponseFixtureResource
{
    public ?string $categoryKey = null;
}
