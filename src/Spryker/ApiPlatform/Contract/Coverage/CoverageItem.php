<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Anything the gate can count: the truth, the claims and the runtime recordings of one dimension
 * are compared by this key and nothing else.
 */
interface CoverageItem
{
    public function key(): string;
}
