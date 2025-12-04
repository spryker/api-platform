<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

class MergeValidationRule implements ValidationRuleInterface
{
    protected const array IMMUTABLE_ATTRIBUTES = ['type', 'identifier'];

    /**
     * @param array<string, mixed> $schema Must contain 'coreSchema' and 'projectSchema' keys
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $coreSchema = $schema['coreSchema'] ?? null;
        $projectSchema = $schema['projectSchema'] ?? null;

        if (!is_array($coreSchema)) {
            return [];
        }

        if (!is_array($projectSchema)) {
            return [];
        }

        $errors = [];

        $errors = array_merge(
            $errors,
            $this->validateImmutableAttributes($coreSchema, $projectSchema),
        );

        $errors = array_merge(
            $errors,
            $this->validateRequiredProperties($coreSchema, $projectSchema),
        );

        return $errors;
    }

    /**
     * @param array<string, mixed> $coreSchema
     * @param array<string, mixed> $projectSchema
     *
     * @return array<string>
     */
    protected function validateImmutableAttributes(array $coreSchema, array $projectSchema): array
    {
        $errors = [];
        $coreProperties = $coreSchema['properties'] ?? [];
        $projectProperties = $projectSchema['properties'] ?? [];

        if (!is_array($coreProperties) || !is_array($projectProperties)) {
            return $errors;
        }

        foreach ($projectProperties as $propertyName => $projectProperty) {
            if (!isset($coreProperties[$propertyName])) {
                continue;
            }

            $coreProperty = $coreProperties[$propertyName];

            foreach (static::IMMUTABLE_ATTRIBUTES as $attribute) {
                $coreValue = $coreProperty[$attribute] ?? null;
                $projectValue = $projectProperty[$attribute] ?? null;

                if ($coreValue !== null && $projectValue !== null && $coreValue !== $projectValue) {
                    // Type and identifier are immutable to prevent breaking changes to generated code/API contracts
                    $errors[] = sprintf(
                        'Project layer cannot modify immutable attribute "%s" for property "%s" in %s. Core value: "%s", Project value: "%s". This prevents breaking changes to generated code and API contracts.',
                        $attribute,
                        $propertyName,
                        $projectSchema['sourceFile'] ?? 'unknown file',
                        $this->formatValue($coreValue),
                        $this->formatValue($projectValue),
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $coreSchema
     * @param array<string, mixed> $projectSchema
     *
     * @return array<string>
     */
    protected function validateRequiredProperties(array $coreSchema, array $projectSchema): array
    {
        $errors = [];
        $coreProperties = $coreSchema['properties'] ?? [];
        $projectProperties = $projectSchema['properties'] ?? [];

        if (!is_array($coreProperties) || !is_array($projectProperties)) {
            return $errors;
        }

        foreach ($coreProperties as $propertyName => $coreProperty) {
            $isRequired = $coreProperty['required'] ?? false;

            if (!$isRequired) {
                continue;
            }

            if (!isset($projectProperties[$propertyName])) {
                // Required properties cannot be removed for API backwards compatibility
                $errors[] = sprintf(
                    'Project layer cannot remove required property "%s" defined in core schema (%s) in %s. This ensures API backwards compatibility.',
                    $propertyName,
                    $coreSchema['sourceFile'] ?? 'unknown file',
                    $projectSchema['sourceFile'] ?? 'unknown file',
                );
            }
        }

        return $errors;
    }

    protected function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string)$value;
    }
}
