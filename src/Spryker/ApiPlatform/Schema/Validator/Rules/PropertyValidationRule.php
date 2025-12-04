<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class PropertyValidationRule implements ValidationRuleInterface
{
    protected const string VALID_PROPERTY_NAME_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    protected const array VALID_PROPERTY_TYPES = ['string', 'integer', 'boolean', 'array', 'object', 'mixed'];

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $errors = [];
        $properties = $schema['properties'] ?? [];

        if (!is_array($properties)) {
            return $errors;
        }

        $errors = array_merge($errors, $this->validatePropertyNames($properties, $schema));
        $errors = array_merge($errors, $this->validatePropertyTypes($properties, $schema));
        $errors = array_merge($errors, $this->validateBooleanAttributes($properties, $schema));
        $errors = array_merge($errors, $this->validateDefaultValues($properties, $schema));
        $errors = array_merge($errors, $this->validateOpenapiContext($properties, $schema));

        return $errors;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validatePropertyNames(array $properties, array $schema): array
    {
        $errors = [];

        foreach (array_keys($properties) as $propertyName) {
            if (!preg_match(static::VALID_PROPERTY_NAME_PATTERN, $propertyName)) {
                $errors[] = sprintf(
                    'Invalid property name "%s" in %s. Property names must start with a letter or underscore, followed by letters, numbers, or underscores.',
                    $propertyName,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $errors;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validatePropertyTypes(array $properties, array $schema): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            $type = $property['type'] ?? null;

            if ($type === null) {
                continue;
            }

            if (!in_array($type, static::VALID_PROPERTY_TYPES, true)) {
                $errors[] = sprintf(
                    'Invalid property type "%s" for property "%s" in %s. Valid types are: %s.',
                    $type,
                    $propertyName,
                    $schema['sourceFile'] ?? 'unknown file',
                    implode(', ', static::VALID_PROPERTY_TYPES),
                );
            }
        }

        return $errors;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateBooleanAttributes(array $properties, array $schema): array
    {
        $errors = [];
        $booleanAttributes = ['writable', 'readable', 'identifier', 'required'];

        foreach ($properties as $propertyName => $property) {
            foreach ($booleanAttributes as $attribute) {
                if (!isset($property[$attribute])) {
                    continue;
                }

                if (!is_bool($property[$attribute])) {
                    $errors[] = sprintf(
                        'Property "%s" attribute "%s" must be a boolean in %s',
                        $propertyName,
                        $attribute,
                        $schema['sourceFile'] ?? 'unknown file',
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateDefaultValues(array $properties, array $schema): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            if (!isset($property['default'])) {
                continue;
            }

            $type = $property['type'] ?? 'string';
            $default = $property['default'];

            if (!$this->isTypeCompatible($default, $type)) {
                $errors[] = sprintf(
                    'Property "%s" default value type does not match declared type "%s" in %s',
                    $propertyName,
                    $type,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $errors;
    }

    protected function isTypeCompatible(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value) || is_array($value),
            'mixed' => true,
            default => true,
        };
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateOpenapiContext(array $properties, array $schema): array
    {
        $errors = [];

        foreach ($properties as $propertyName => $property) {
            if (!isset($property['openapiContext'])) {
                continue;
            }

            if (!is_array($property['openapiContext'])) {
                $errors[] = sprintf(
                    'Property "%s" openapiContext must be an array in %s',
                    $propertyName,
                    $schema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $errors;
    }
}
