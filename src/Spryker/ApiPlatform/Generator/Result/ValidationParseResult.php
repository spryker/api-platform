<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Result;

class ValidationParseResult
{
    /**
     * @param array<string, array<array{schema: array<string, mixed>, sourceFile: string}>> $validationSchemas
     * @param array<array{file: string, error: string}> $failedFiles
     */
    public function __construct(
        protected readonly array $validationSchemas,
        protected readonly array $failedFiles,
    ) {
    }

    /**
     * @return array<string, array<array{schema: array<string, mixed>, sourceFile: string}>>
     */
    public function getValidationSchemas(): array
    {
        return $this->validationSchemas;
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
