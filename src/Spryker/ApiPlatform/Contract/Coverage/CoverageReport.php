<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The outcome of diffing a {@see TruthSet} against {@see CollectedAnnotations}: what is covered,
 * what is an uncovered gap, and which claims are stale (point at something the generated truth no
 * longer contains). A build gate fails when any gap or stale claim exists.
 *
 * Response attributes know no stale bucket: they are derived from the enforced truth and claimed
 * per operation by a marker, so a claim can only ever point at an operation the truth defines.
 */
readonly class CoverageReport
{
    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $coveredOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $uncoveredOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $staleOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $coveredValidations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $uncoveredValidations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $staleValidations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $coveredResponseAttributes
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $uncoveredResponseAttributes
     */
    public function __construct(
        public array $coveredOperations,
        public array $uncoveredOperations,
        public array $staleOperations,
        public array $coveredValidations,
        public array $uncoveredValidations,
        public array $staleValidations,
        public array $coveredResponseAttributes = [],
        public array $uncoveredResponseAttributes = [],
    ) {
    }

    public function hasFailures(): bool
    {
        return $this->uncoveredOperations !== []
            || $this->staleOperations !== []
            || $this->uncoveredValidations !== []
            || $this->staleValidations !== []
            || $this->uncoveredResponseAttributes !== [];
    }
}
