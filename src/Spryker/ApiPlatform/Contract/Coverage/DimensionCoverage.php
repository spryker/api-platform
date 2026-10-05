<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The diff of one coverage dimension: the truth items a claim covers, those no claim covers, and the
 * claims that point at nothing the schema defines. Once the {@see ContractCoverageBaseline} is
 * applied, it also carries the uncovered items a known bug excuses and the baseline entries that
 * have to be removed.
 *
 * @template-covariant TItem of \Spryker\ApiPlatform\Contract\Coverage\CoverageItem
 */
readonly class DimensionCoverage
{
    /**
     * @param array<TItem> $covered
     * @param array<TItem> $uncovered
     * @param array<TItem> $stale
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\BaselineEntry> $baselined Uncovered items the baseline excuses.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\BaselineEntry> $baselineEntriesNowCovered Baseline entries whose item a claim covers.
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\BaselineEntry> $baselineEntriesNamingNoGap Baseline entries that name no uncovered item.
     */
    public function __construct(
        public array $covered = [],
        public array $uncovered = [],
        public array $stale = [],
        public array $baselined = [],
        public array $baselineEntriesNowCovered = [],
        public array $baselineEntriesNamingNoGap = [],
    ) {
    }

    public function hasGaps(): bool
    {
        return $this->uncovered !== [] || $this->stale !== [];
    }

    /**
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\BaselineEntry>
     */
    public function baselineEntriesToRemove(): array
    {
        return [...$this->baselineEntriesNowCovered, ...$this->baselineEntriesNamingNoGap];
    }
}
