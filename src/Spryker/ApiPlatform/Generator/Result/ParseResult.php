<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Result;

class ParseResult
{
    /**
     * @param array<string, array<array<string, mixed>>> $groupedSchemas
     * @param array<array{file: string, error: string}> $failedValidationFiles
     * @param array<array{file: string, error: string}> $failedSchemaFiles
     */
    public function __construct(
        protected readonly array $groupedSchemas,
        protected readonly array $failedValidationFiles,
        protected readonly array $failedSchemaFiles,
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
    public function getFailedValidationFiles(): array
    {
        return $this->failedValidationFiles;
    }

    /**
     * @return array<array{file: string, error: string}>
     */
    public function getFailedSchemaFiles(): array
    {
        return $this->failedSchemaFiles;
    }

    public function hasFailures(): bool
    {
        return $this->failedValidationFiles !== [] || $this->failedSchemaFiles !== [];
    }
}
