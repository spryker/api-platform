<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Utility;

class FractionalNumberDetector
{
    /**
     * True for a float or a numeric string with a fractional part: `1.5`, `"1.5"`, `"-0.1"`.
     * False for every value PHP converts to an integer without loss, such as `2.0`, `"2"`, `"2.0"`
     * and `"1e3"`, and for anything that is not a number at all.
     */
    public static function isFractional(mixed $value): bool
    {
        if (is_string($value) && is_numeric($value)) {
            $value = +$value;
        }

        return is_float($value) && is_finite($value) && floor($value) !== $value;
    }
}
