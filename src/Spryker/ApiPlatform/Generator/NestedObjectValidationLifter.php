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
 *
 * Lifting recurses into fields that denormalize into value objects of their own, so only leaves ever
 * carry value constraints. Descent stops on an `objectName` already lifted on the current path, so a
 * self-referential definition cascades instead of recursing forever.
 */
class NestedObjectValidationLifter
{
    /**
     * `All` belongs here because a collection of value objects declares its per-element `Collection`
     * inside it — without it such a field is mistaken for a leaf.
     *
     * @var array<string>
     */
    protected const array COLLECTION_FIELD_WRAPPERS = [self::WRAPPER_OPTIONAL, self::WRAPPER_REQUIRED, self::WRAPPER_ALL];

    protected const string WRAPPER_ALL = 'All';

    protected const string WRAPPER_REQUIRED = 'Required';

    protected const string WRAPPER_OPTIONAL = 'Optional';

    /**
     * Symfony rejects `Valid` nested inside any `Composite` constraint, and these two wrappers reach
     * the attribute emitter verbatim — so a cascade produced under them is hoisted out to sit
     * alongside the wrapper rather than inside it. `Optional` is absent on purpose: it keeps its
     * cascade because {@see ValidationAttributeGenerator} unwraps that wrapper later to emit the
     * optional payload marker.
     *
     * @var array<string>
     */
    protected const array VALID_HOISTING_WRAPPERS = [self::WRAPPER_ALL, self::WRAPPER_REQUIRED];

    protected const string VALID_CONSTRAINT_NAME = 'Valid';

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
        return $this->liftFieldsInto($nestedProperties, $validationSchema, $operations, $propertyName, $resourceName, []);
    }

    /**
     * @param array<string, array<string, mixed>> $nestedProperties
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     * @param array<string, true> $objectNamesOnPath Object names already lifted above this level.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function liftFieldsInto(
        array $nestedProperties,
        array $validationSchema,
        array $operations,
        string $propertyName,
        string $resourceName,
        array $objectNamesOnPath
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

            if ($collection['allowMissingFields']) {
                $fields = $this->wrapFieldsInOptional($fields);
            }

            foreach ($fields as $fieldName => $fieldConstraints) {
                $fieldSchema[$httpMethod][(string)$fieldName] = $fieldConstraints;
                $fieldNames[(string)$fieldName] = true;
            }
        }

        foreach (array_keys($fieldNames) as $fieldName) {
            $fieldName = (string)$fieldName;

            if (!isset($nestedProperties[$fieldName])) {
                continue;
            }

            $nestedProperties[$fieldName] = $this->liftField(
                $nestedProperties[$fieldName],
                $fieldSchema,
                $operations,
                $fieldName,
                $resourceName,
                $objectNamesOnPath,
            );
        }

        return $nestedProperties;
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, mixed> $fieldSchema
     * @param array<string, mixed> $operations
     * @param array<string, true> $objectNamesOnPath
     *
     * @return array<string, mixed>
     */
    protected function liftField(
        array $property,
        array $fieldSchema,
        array $operations,
        string $fieldName,
        string $resourceName,
        array $objectNamesOnPath
    ): array {
        $childProperties = $this->extractChildProperties($property);

        if ($childProperties === null || !$this->hasCollectionValidation($fieldSchema, $fieldName)) {
            $attributes = $this->validationAttributeGenerator->generate($fieldSchema, $operations, $fieldName, $resourceName);

            if ($attributes !== []) {
                $property['_validationAttributes'] = array_values($attributes);
            }

            return $property;
        }

        $objectName = $property['objectName'] ?? null;
        $objectName = is_string($objectName) && $objectName !== '' ? $objectName : null;

        // A self-referencing shared object is lifted where its canonical class is generated.
        if ($objectName === null || !isset($objectNamesOnPath[$objectName])) {
            if ($objectName !== null) {
                $objectNamesOnPath[$objectName] = true;
            }

            $property = $this->writeChildProperties($property, $this->liftFieldsInto(
                $childProperties,
                $fieldSchema,
                $operations,
                $fieldName,
                $resourceName,
                $objectNamesOnPath,
            ));
        }

        $cascadeSchema = $this->replaceCollectionWithValidCascade($fieldSchema, $fieldName);
        $attributes = $this->validationAttributeGenerator->generate($cascadeSchema, $operations, $fieldName, $resourceName);

        if ($attributes !== []) {
            $property['_validationAttributes'] = array_values($attributes);
        }

        return $property;
    }

    /**
     * Null for every field that stays a scalar, a plain array or an untyped object.
     *
     * @param array<string, mixed> $property
     *
     * @return array<string, array<string, mixed>>|null
     */
    protected function extractChildProperties(array $property): ?array
    {
        $type = $property['type'] ?? null;

        if ($type === 'object' && isset($property['properties']) && is_array($property['properties'])) {
            return $property['properties'];
        }

        if (
            $type === 'array'
            && isset($property['items'])
            && is_array($property['items'])
            && ($property['items']['type'] ?? null) === 'object'
            && isset($property['items']['properties'])
            && is_array($property['items']['properties'])
        ) {
            return $property['items']['properties'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, array<string, mixed>> $childProperties
     *
     * @return array<string, mixed>
     */
    protected function writeChildProperties(array $property, array $childProperties): array
    {
        if (($property['type'] ?? null) === 'object') {
            $property['properties'] = $childProperties;

            return $property;
        }

        $property['items']['properties'] = $childProperties;

        return $property;
    }

    /**
     * Swaps the field's `Collection` — at any wrapper depth — for a bare `Valid`, leaving siblings untouched.
     *
     * @param array<string, mixed> $fieldSchema
     *
     * @return array<string, mixed>
     */
    protected function replaceCollectionWithValidCascade(array $fieldSchema, string $fieldName): array
    {
        foreach ($fieldSchema as $httpMethod => $fieldConstraints) {
            if (!is_array($fieldConstraints) || !isset($fieldConstraints[$fieldName]) || !is_array($fieldConstraints[$fieldName])) {
                continue;
            }

            $fieldSchema[$httpMethod][$fieldName] = $this->replaceCollectionConstraint($fieldConstraints[$fieldName]);
        }

        return $fieldSchema;
    }

    /**
     * @param array<array-key, mixed> $constraints
     *
     * @return array<array-key, mixed>
     */
    protected function replaceCollectionConstraint(array $constraints): array
    {
        $replacedConstraints = [];

        foreach ($constraints as $constraint) {
            // A hoisted cascade turns one entry into two, so the list is rebuilt rather than
            // rewritten in place. Constraint lists are consumed by iteration only, never by key.
            foreach ($this->replaceConstraint($constraint) as $replacedConstraint) {
                $replacedConstraints[] = $replacedConstraint;
            }
        }

        return $replacedConstraints;
    }

    /**
     * @return array<int, mixed> Normally one entry; two when a hoisted cascade leaves its wrapper in place.
     */
    protected function replaceConstraint(mixed $constraint): array
    {
        if (!is_array($constraint)) {
            return [$constraint];
        }

        if (isset($constraint['Collection']['fields']) && is_array($constraint['Collection']['fields'])) {
            return [static::VALID_CONSTRAINT_NAME];
        }

        foreach (static::COLLECTION_FIELD_WRAPPERS as $wrapper) {
            if (!isset($constraint[$wrapper]['constraints']) || !is_array($constraint[$wrapper]['constraints'])) {
                continue;
            }

            return $this->replaceWrappedConstraint($constraint, $wrapper);
        }

        return [$constraint];
    }

    /**
     * @param array<array-key, mixed> $constraint
     *
     * @return array<int, mixed>
     */
    protected function replaceWrappedConstraint(array $constraint, string $wrapper): array
    {
        $wrappedConstraints = $this->replaceCollectionConstraint($constraint[$wrapper]['constraints']);
        $hasCascade = in_array(static::VALID_CONSTRAINT_NAME, $wrappedConstraints, true);

        if (!$hasCascade || !in_array($wrapper, static::VALID_HOISTING_WRAPPERS, true)) {
            $constraint[$wrapper]['constraints'] = $wrappedConstraints;

            return [$constraint];
        }

        // `Valid` cascades element-wise over an array by itself, so it is lifted out of the wrapper
        // Symfony would reject it in. Everything else the wrapper held still applies per element and
        // stays inside it — dropping those would silently discard declared validation.
        $validConstraintName = static::VALID_CONSTRAINT_NAME;
        $siblingConstraints = array_values(array_filter(
            $wrappedConstraints,
            static fn (mixed $wrappedConstraint): bool => $wrappedConstraint !== $validConstraintName,
        ));

        if ($siblingConstraints === []) {
            return [static::VALID_CONSTRAINT_NAME];
        }

        $constraint[$wrapper]['constraints'] = $siblingConstraints;

        return [$constraint, static::VALID_CONSTRAINT_NAME];
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
     * Unwraps the {@see COLLECTION_FIELD_WRAPPERS} to find the `Collection` constraint and return its
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

            foreach (static::COLLECTION_FIELD_WRAPPERS as $wrapper) {
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
     * `allowMissingFields: true` is per-field `Optional` applied to every key, so it reuses that
     * wrapper: the constraints stay as declared and
     * {@see \Spryker\ApiPlatform\State\OptionalFieldFilteringValidateProvider} decides absence from
     * the request body, which is the only place absent and explicit-null still differ.
     *
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    protected function wrapFieldsInOptional(array $fields): array
    {
        foreach ($fields as $fieldName => $constraints) {
            if (!is_array($constraints) || $this->hasOptionalWrapper($constraints)) {
                continue;
            }

            $fields[$fieldName] = [[static::WRAPPER_OPTIONAL => ['constraints' => $constraints]]];
        }

        return $fields;
    }

    /**
     * Double wrapping would leave an inner `Optional` behind after the generator unwraps the outer
     * one, emitting `#[Assert\Optional(...)]` — not a valid attribute outside a `Collection`.
     *
     * @param array<array-key, mixed> $constraints
     */
    protected function hasOptionalWrapper(array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if (is_array($constraint) && array_key_exists(static::WRAPPER_OPTIONAL, $constraint)) {
                return true;
            }
        }

        return false;
    }
}
