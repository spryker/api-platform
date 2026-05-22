<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class SchemaCompletenessValidationRule implements ValidationRuleInterface
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
        $properties = $schema['properties'] ?? [];
        $includes = $schema['includes'] ?? [];

        $hasOperations = is_array($operations) && $operations !== [];
        $hasProperties = is_array($properties) && $properties !== [];
        $hasIncludes = is_array($includes) && $includes !== [];

        if (!$hasOperations && !$hasProperties && !$hasIncludes) {
            return [
                sprintf(
                    'Schema in %s must define at least one of: operations, properties, includes.',
                    $schema['sourceFile'] ?? 'unknown file',
                ),
            ];
        }

        if (!$hasOperations) {
            return $errors;
        }

        foreach ($operations as $key => $operation) {
            $operationType = is_array($operation) ? ($operation['type'] ?? $key) : $key;

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
