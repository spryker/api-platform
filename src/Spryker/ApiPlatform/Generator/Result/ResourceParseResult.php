<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Result;

class ResourceParseResult
{
    /**
     * @param array<string, array<array<string, mixed>>> $groupedSchemas
     * @param array<array{file: string, error: string}> $failedFiles
     */
    public function __construct(
        protected readonly array $groupedSchemas,
        protected readonly array $failedFiles,
    ) {
    }

    /**
     * @return array<string, array<array<string, mixed>>>
     */
    public function getGroupedSchemas(): array
    {
        return $this->groupedSchemas;
    }

    /**
     * @return array<array{file: string, error: string}>
     */
    public function getFailedFiles(): array
    {
        return $this->failedFiles;
    }

    public function hasFailures(): bool
    {
        return $this->failedFiles !== [];
    }
}
