<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

use Spryker\ApiPlatform\Validation\Constraint\StrictBoolean;

class StrictBooleanFixture
{
    public const string MESSAGE = 'validation.type.bool';

    #[StrictBoolean(message: self::MESSAGE)]
    public ?bool $isActive = null;
}
