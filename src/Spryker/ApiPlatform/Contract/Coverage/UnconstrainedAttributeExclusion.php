<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * An entry of `contract_coverage_excluded_unconstrained_attributes`, `<resource>.<attribute path>`.
 * It is reported stale once it names no typed writable attribute, so an opt-out cannot outlive the
 * attribute it was written for.
 */
readonly class UnconstrainedAttributeExclusion implements CoverageItem
{
    public function __construct(public string $exclusionKey)
    {
    }

    public function key(): string
    {
        return $this->exclusionKey;
    }
}
