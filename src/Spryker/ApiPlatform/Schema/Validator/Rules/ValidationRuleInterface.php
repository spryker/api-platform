<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

/**
 * Base interface for schema validation rules.
 */
interface ValidationRuleInterface
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string> Validation error messages
     */
    public function validate(array $schema): array;
}
