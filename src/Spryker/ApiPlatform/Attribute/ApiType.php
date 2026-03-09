<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Attribute;

use Attribute;

/**
 * Restricts a service to specific API types (e.g. storefront, backend).
 *
 * Services annotated with this attribute are automatically tagged during container compilation
 * and removed if the current application's configured API types do not intersect with the
 * attribute's types.
 */
#[\Attribute(Attribute::TARGET_CLASS)]
class ApiType
{
    /**
     * @param array<string> $types
     */
    public function __construct(public readonly array $types)
    {
    }
}
