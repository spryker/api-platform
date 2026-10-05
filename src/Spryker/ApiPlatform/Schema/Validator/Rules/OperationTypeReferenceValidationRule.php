<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

use Spryker\ApiPlatform\Generator\SchemaKey;

/**
 * Rejects a `writableOn` that names an operation type the resource does not declare as a write, and
 * an `includedOn` that names an operation type the resource does not declare: a property or an
 * include narrowed to an operation that does not exist is demanded of no operation, silently.
 */
class OperationTypeReferenceValidationRule implements ValidationRuleInterface
{
    protected const string KEY_OPERATIONS = 'operations';

    protected const string KEY_TYPE = 'type';

    protected const string KEY_INCLUDES = 'includes';

    protected const string KEY_RELATIONSHIP_NAME = 'relationshipName';

    /**
     * @var array<string>
     */
    protected const array INPUT_OPERATION_TYPES = ['Post', 'Put', 'Patch'];

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $declaredInputTypes = array_values(array_intersect($this->declaredOperationTypes($schema), static::INPUT_OPERATION_TYPES));

        return [
            ...$this->validateWritableOn((array)($schema[SchemaKey::PROPERTIES] ?? []), '', $declaredInputTypes, $this->getSourceFile($schema)),
            ...$this->validateIncludedOn($schema),
        ];
    }

    /**
     * Walks nested `properties` and `items.properties` too: a nested property narrowed to an
     * operation that does not exist drops out of request-attribute coverage just as silently.
     *
     * @param array<mixed> $properties
     * @param array<string> $declaredInputTypes
     *
     * @return array<string>
     */
    protected function validateWritableOn(array $properties, string $pathPrefix, array $declaredInputTypes, string $sourceFile): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyPath = $pathPrefix . (string)$propertyName;
            $writableOn = $property[SchemaKey::WRITABLE_ON] ?? [];

            if (!is_array($writableOn)) {
                $errors[] = sprintf('Property "%s" in %s declares writableOn, which must be a list of operation types.', $propertyPath, $sourceFile);
                $writableOn = [];
            }

            foreach ($writableOn as $operationType) {
                if (!in_array($operationType, $declaredInputTypes, true)) {
                    $errors[] = sprintf(
                        'Property "%s" in %s is writableOn "%s", which is not a write operation the resource declares (declared: %s).',
                        $propertyPath,
                        $sourceFile,
                        is_scalar($operationType) ? (string)$operationType : '',
                        $declaredInputTypes === [] ? 'none' : implode(', ', $declaredInputTypes),
                    );
                }
            }

            $nestedProperties = $property[SchemaKey::PROPERTIES] ?? ($property[SchemaKey::ITEMS][SchemaKey::PROPERTIES] ?? null);
            if (is_array($nestedProperties)) {
                $errors = [...$errors, ...$this->validateWritableOn($nestedProperties, $propertyPath . '.', $declaredInputTypes, $sourceFile)];
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateIncludedOn(array $schema): array
    {
        $declaredTypes = $this->declaredOperationTypes($schema);
        $errors = [];

        foreach ((array)($schema[static::KEY_INCLUDES] ?? []) as $include) {
            $includedOn = is_array($include) ? ($include[SchemaKey::INCLUDED_ON] ?? null) : null;
            if ($includedOn === null) {
                continue;
            }

            if (!is_array($includedOn)) {
                $errors[] = sprintf(
                    'Include "%s" in %s declares includedOn, which must be a list of operation types.',
                    (string)($include[static::KEY_RELATIONSHIP_NAME] ?? ''),
                    $this->getSourceFile($schema),
                );

                continue;
            }

            foreach ($includedOn as $operationType) {
                if (!in_array($operationType, $declaredTypes, true)) {
                    $errors[] = sprintf(
                        'Include "%s" in %s is includedOn "%s", which is not an operation the resource declares (declared: %s).',
                        (string)($include[static::KEY_RELATIONSHIP_NAME] ?? ''),
                        $this->getSourceFile($schema),
                        is_scalar($operationType) ? (string)$operationType : '',
                        $declaredTypes === [] ? 'none' : implode(', ', $declaredTypes),
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function declaredOperationTypes(array $schema): array
    {
        $types = [];

        foreach ((array)($schema[static::KEY_OPERATIONS] ?? []) as $key => $operation) {
            $types[] = (string)(is_array($operation) ? ($operation[static::KEY_TYPE] ?? $key) : $key);
        }

        return $types;
    }

    /**
     * @param array<string, mixed> $schema
     */
    protected function getSourceFile(array $schema): string
    {
        return (string)($schema[SchemaKey::SOURCE_FILE] ?? 'unknown file');
    }
}
