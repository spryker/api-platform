<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Result;

class ValidationResult
{
    /**
     * @param array<string, array<string, mixed>> $validatedSchemas
     * @param array<array{resource: string, error: string}> $failedValidations
     */
    public function __construct(
        protected readonly array $validatedSchemas,
        protected readonly array $failedValidations,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getValidatedSchemas(): array
    {
        return $this->validatedSchemas;
    }

    /**
     * @return array<array{resource: string, error: string}>
     */
    public function getFailedValidations(): array
    {
        return $this->failedValidations;
    }

    public function hasFailures(): bool
    {
        return $this->failedValidations !== [];
    }
}
