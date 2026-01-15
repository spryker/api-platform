<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Result;

class MergeResult
{
    /**
     * @param array<string, array<string, mixed>> $mergedSchemas
     * @param array<array{resource: string, error: string}> $failedMerges
     */
    public function __construct(
        protected readonly array $mergedSchemas,
        protected readonly array $failedMerges,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getMergedSchemas(): array
    {
        return $this->mergedSchemas;
    }

    /**
     * @return array<array{resource: string, error: string}>
     */
    public function getFailedMerges(): array
    {
        return $this->failedMerges;
    }

    public function hasFailures(): bool
    {
        return $this->failedMerges !== [];
    }
}
