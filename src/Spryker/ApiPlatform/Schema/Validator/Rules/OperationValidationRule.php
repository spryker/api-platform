<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class OperationValidationRule implements ValidationRuleInterface
{
    protected const array VALID_OPERATIONS = ['Get', 'GetCollection', 'Post', 'Put', 'Patch', 'Delete'];

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $errors = [];
        $operations = $schema['operations'] ?? [];

        if (!is_array($operations) || $operations === []) {
            return [
                sprintf(
                    'At least one operation must be defined in %s',
                    $schema['sourceFile'] ?? 'unknown file',
                ),
            ];
        }

        foreach (array_keys($operations) as $operationType) {
            if (!in_array($operationType, static::VALID_OPERATIONS, true)) {
                $errors[] = sprintf(
                    'Invalid operation type "%s" in %s. Valid types are: %s',
                    $operationType,
                    $schema['sourceFile'] ?? 'unknown file',
                    implode(', ', static::VALID_OPERATIONS),
                );
            }
        }

        return $errors;
    }
}
