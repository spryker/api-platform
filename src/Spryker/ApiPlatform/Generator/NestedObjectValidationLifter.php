<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * Lifts the per-field validation declared on an object property's `Collection` constraint onto the
 * generated value-object class.
 *
 * A writable nested object (e.g. checkout `customer`/`billingAddress`) denormalizes into a typed
 * value object, so the parent property can no longer carry an array-shaped `Collection` constraint —
 * that constraint would reject the object. Instead the field-level constraints are re-formatted into
 * `#[Assert\...]` attribute strings and attached to each value-object field (under the
 * `_validationAttributes` key the value-object renderer reads), while the parent property carries a
 * plain `#[Assert\Valid]` cascade.
 */
class NestedObjectValidationLifter
{
    public function __construct(
        protected readonly ValidationAttributeGenerator $validationAttributeGenerator,
    ) {
    }

    /**
     * Attaches the lifted `#[Assert\...]` attribute strings to their matching nested properties.
     *
     * @param array<string, array<string, mixed>> $nestedProperties The value object's own properties.
     * @param array<string, mixed> $validationSchema Post-merge validation, keyed by HTTP method.
     * @param array<string, mixed> $operations
     *
     * @return array<string, array<string, mixed>>
     */
    public function lift(
        array $nestedProperties,
        array $validationSchema,
        array $operations,
        string $propertyName,
        string $resourceName
    ): array {
        $fieldSchema = [];
        $fieldNames = [];

        foreach ($validationSchema as $httpMethod => $propertyConstraints) {
            if (!is_array($propertyConstraints) || !isset($propertyConstraints[$propertyName])) {
                continue;
            }

            $collection = $this->extractCollection($propertyConstraints[$propertyName]);

            if ($collection === null) {
                continue;
            }

            $fields = $collection['fields'];

            // `allowMissingFields: true` (e.g. checkout billingAddress referenced by id) tolerates
            // absent keys. On a value object an absent field denormalizes to null, so relax NotBlank
            // to `allowNull: true` — an absent field passes, a present-but-empty one still fails.
            if ($collection['allowMissingFields']) {
                $fields = $this->relaxRequiredFieldConstraints($fields);
            }

            foreach ($fields as $fieldName => $fieldConstraints) {
                $fieldSchema[$httpMethod][(string)$fieldName] = $fieldConstraints;
                $fieldNames[(string)$fieldName] = true;
            }
        }

        foreach (array_keys($fieldNames) as $fieldName) {
            if (!isset($nestedProperties[$fieldName])) {
                continue;
            }

            $attributes = $this->validationAttributeGenerator->generate($fieldSchema, $operations, (string)$fieldName, $resourceName);

            if ($attributes !== []) {
                $nestedProperties[$fieldName]['_validationAttributes'] = array_values($attributes);
            }
        }

        return $nestedProperties;
    }

    /**
     * Reports whether the property has any `Collection` validation worth lifting (and therefore a
     * `#[Assert\Valid]` cascade) under at least one operation.
     *
     * @param array<string, mixed> $validationSchema Post-merge validation, keyed by HTTP method.
     */
    public function hasCollectionValidation(array $validationSchema, string $propertyName): bool
    {
        foreach ($validationSchema as $propertyConstraints) {
            if (!is_array($propertyConstraints) || !isset($propertyConstraints[$propertyName])) {
                continue;
            }

            if ($this->extractCollection($propertyConstraints[$propertyName]) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unwraps `Optional`/`Required` wrappers to find the `Collection` constraint and return its
     * `fields` map plus its `allowMissingFields` flag.
     *
     * @return array{fields: array<array-key, mixed>, allowMissingFields: bool}|null
     */
    protected function extractCollection(mixed $constraints): ?array
    {
        if (!is_array($constraints)) {
            return null;
        }

        foreach ($constraints as $constraint) {
            if (!is_array($constraint)) {
                continue;
            }

            if (isset($constraint['Collection']['fields']) && is_array($constraint['Collection']['fields'])) {
                return [
                    'fields' => $constraint['Collection']['fields'],
                    'allowMissingFields' => (bool)($constraint['Collection']['allowMissingFields'] ?? false),
                ];
            }

            foreach (['Optional', 'Required'] as $wrapper) {
                if (!isset($constraint[$wrapper]['constraints']) || !is_array($constraint[$wrapper]['constraints'])) {
                    continue;
                }

                $inner = $this->extractCollection($constraint[$wrapper]['constraints']);

                if ($inner !== null) {
                    return $inner;
                }
            }
        }

        return null;
    }

    /**
     * Relaxes presence constraints for `allowMissingFields: true`: each `NotBlank` gains
     * `allowNull: true` and each `NotNull` is dropped, so an absent (null) field is tolerated while a
     * present-but-empty value is still rejected. Recurses through `Optional`/`Required` wrappers.
     *
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    protected function relaxRequiredFieldConstraints(array $fields): array
    {
        foreach ($fields as $fieldName => $constraints) {
            if (is_array($constraints)) {
                $fields[$fieldName] = $this->relaxConstraintList($constraints);
            }
        }

        return $fields;
    }

    /**
     * @param array<array-key, mixed> $constraints
     *
     * @return array<array-key, mixed>
     */
    protected function relaxConstraintList(array $constraints): array
    {
        $relaxed = [];

        foreach ($constraints as $constraint) {
            if ($constraint === 'NotNull' || (is_array($constraint) && array_key_first($constraint) === 'NotNull')) {
                continue;
            }

            if ($constraint === 'NotBlank') {
                $relaxed[] = ['NotBlank' => ['allowNull' => true]];

                continue;
            }

            if (is_array($constraint) && array_key_first($constraint) === 'NotBlank') {
                $options = is_array($constraint['NotBlank']) ? $constraint['NotBlank'] : [];
                $options['allowNull'] = true;
                $relaxed[] = ['NotBlank' => $options];

                continue;
            }

            foreach (['Optional', 'Required'] as $wrapper) {
                if (is_array($constraint) && isset($constraint[$wrapper]['constraints']) && is_array($constraint[$wrapper]['constraints'])) {
                    $constraint[$wrapper]['constraints'] = $this->relaxConstraintList($constraint[$wrapper]['constraints']);
                }
            }

            $relaxed[] = $constraint;
        }

        return $relaxed;
    }
}
