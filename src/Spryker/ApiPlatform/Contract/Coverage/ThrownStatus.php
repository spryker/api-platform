<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The unit of thrown-status coverage: an operation's dispatch key paired with an error status its
 * processor or provider can throw, which the operation's schema has to declare.
 */
readonly class ThrownStatus implements CoverageItem
{
    public function __construct(public string $dispatchKey, public int $status)
    {
    }

    public function key(): string
    {
        return $this->dispatchKey . ' ' . $this->status;
    }
}
