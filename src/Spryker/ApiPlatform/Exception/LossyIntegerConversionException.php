<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Exception;

use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;

/**
 * Extends the PropertyAccess exception family, so the serializer's object normalizers wrap it into a
 * `NotNormalizableValueException` like every other type mismatch on a write.
 */
class LossyIntegerConversionException extends InvalidArgumentException
{
    protected const string MESSAGE = 'Expected an integer, a number with a fractional part given at property path "%s".';

    public function __construct(public readonly string $propertyPath)
    {
        parent::__construct(sprintf(static::MESSAGE, $propertyPath));
    }
}
