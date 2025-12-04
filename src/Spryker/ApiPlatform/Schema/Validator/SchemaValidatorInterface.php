<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator;

/**
 * Main validator orchestration that combines all validation rules.
 */
interface SchemaValidatorInterface
{
    /**
     * @param array<string, mixed> $mergedSchema
     * @param array<string, mixed>|null $coreSchema
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     */
    public function validatePostMerge(array $mergedSchema, ?array $coreSchema = null): void;
}
