<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * One entry of the {@see ContractCoverageBaseline}: the key of a coverage item a known product bug
 * keeps uncovered, and the bug in plain language.
 */
readonly class BaselineEntry implements CoverageItem
{
    public function __construct(
        public string $itemKey,
        public string $reason,
    ) {
    }

    public function key(): string
    {
        return $this->itemKey;
    }
}
