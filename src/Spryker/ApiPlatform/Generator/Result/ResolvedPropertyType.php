<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Result;

/**
 * The PHP type a schema property resolves to, plus — for an object collection — the fully qualified
 * value-object class its elements denormalize into. PHP has no generics, so a collection resolves to
 * `array` and the element class travels separately: the generators turn it into an `@var array<…>`
 * docblock, which is what makes API Platform publish `items.$ref` for the property.
 */
class ResolvedPropertyType
{
    public function __construct(
        public readonly string $phpType,
        public readonly ?string $itemClassFqcn = null,
    ) {
    }
}
