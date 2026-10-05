<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The unit of example-replay coverage: a resource with at least one servable operation, which an
 * example-replay test class has to name.
 */
readonly class ReplayedResource implements CoverageItem
{
    public function __construct(public string $resourceShortName)
    {
    }

    public function key(): string
    {
        return $this->resourceShortName;
    }
}
