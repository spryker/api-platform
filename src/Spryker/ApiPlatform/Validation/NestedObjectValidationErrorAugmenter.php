<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Augments validation for present-but-empty nested value objects (generated `objectName`
 * canonical objects typed as `?Generated\Api\Storefront\*` and cascaded via `Assert\Valid`).
 *
 * Three behaviors, all driven off the RAW submitted body so coercion and `allowNull` cannot
 * erase the submitted shape:
 *
 * - Coerced bool leaf: a nested `?bool` leaf (e.g. `productConfigurationInstance.isComplete`)
 *   submitted as a non-boolean (`1`, `"True"`) is coerced before `Assert\Type` runs, so the Type
 *   error never fires — re-check the raw value and append the boolean Type error.
 * - Relabel flagged leaf: a required leaf (NotBlank/NotNull/Email) of a present-but-empty object
 *   that the validator already flagged is relabeled to "This field is missing." (the top-level
 *   rewrite the subscriber performs skips dotted, multi-error paths).
 * - Synthesize missing leaf: a required leaf of a present-but-empty object whose constraint allows
 *   null (a required leaf whose constraint allows null) produces NO error and the request passes —
 *   synthesize a "This field is missing." error per absent required leaf and force a 422 response.
 *
 * Only nested objects whose parent key IS present in the raw body are touched — an entirely
 * omitted object stays valid (`?Object` null, `Assert\Valid` skips) per legacy behavior.
 *
 * A pure transformer: it takes the already-decoded errors and returns the augmented set. All HTTP
 * request/response handling stays in the caller.
 */
class NestedObjectValidationErrorAugmenter
{
    protected const string ERROR_CODE_VALIDATION = '901';

    protected const string FIELD_MISSING_MESSAGE = 'This field is missing.';

    protected const string TYPE_BOOLEAN_ERROR_MESSAGE = 'This value should be of type boolean.';

    protected const string GENERATED_VALUE_OBJECT_NAMESPACE = 'Generated\\Api\\Storefront\\';

    /**
     * `sprintf` template for a regex that matches a nested-leaf detail prefix `<object>.<leaf> => `.
     * Both segments are interpolated through `preg_quote()` at the call site.
     */
    protected const string REGEX_NESTED_LEAF_DETAIL_PREFIX_TEMPLATE = '/^%s\.%s => /';

    public function __construct(protected ValidationConstraintReader $constraintReader)
    {
    }

    /**
     * @param array<string, mixed> $rawAttributes
     * @param array<string> $groups
     * @param array<int, array<string, mixed>> $errors
     */
    public function augment(
        string $resourceClass,
        array $rawAttributes,
        array $groups,
        array $errors,
    ): NestedObjectAugmentationResult {
        if (!class_exists($resourceClass)) {
            return new NestedObjectAugmentationResult(false, $errors, false);
        }

        $nestedProperties = $this->resolveNestedObjectProperties($resourceClass);

        if ($nestedProperties === []) {
            return new NestedObjectAugmentationResult(false, $errors, false);
        }

        $existingDetails = [];

        foreach ($errors as $error) {
            $existingDetails[$error['detail'] ?? ''] = true;
        }

        $modified = false;
        $hasEmptyRequiredObject = false;

        foreach ($nestedProperties as $propertyName => $valueObjectClass) {
            if (!array_key_exists($propertyName, $rawAttributes) || !is_array($rawAttributes[$propertyName])) {
                continue;
            }

            $submittedObject = $rawAttributes[$propertyName];

            if ($submittedObject === [] && $this->synthesizesMissingFieldsWhenEmpty($valueObjectClass)) {
                $hasEmptyRequiredObject = true;
            }

            $modified = $this->augmentNestedBoolLeaves($errors, $existingDetails, $propertyName, $valueObjectClass, $submittedObject) || $modified;
            $modified = $this->augmentNestedMissingLeaves(
                $errors,
                $existingDetails,
                $propertyName,
                $valueObjectClass,
                $submittedObject,
                $groups,
            ) || $modified;
        }

        if (!$modified) {
            return new NestedObjectAugmentationResult(false, $errors, false);
        }

        $finalErrors = array_values($errors);

        // A required nested object submitted empty ({}) is a validation failure that must supersede
        // any downstream domain error the request produced by wrongly proceeding past validation on a
        // write path. Keep only the validation errors so the response is the pure field-missing set the
        // required-object contract expects.
        if ($hasEmptyRequiredObject) {
            $finalErrors = array_values(array_filter(
                $finalErrors,
                fn (array $error): bool => ($error['code'] ?? null) === static::ERROR_CODE_VALIDATION,
            ));
        }

        return new NestedObjectAugmentationResult(true, $finalErrors, $hasEmptyRequiredObject);
    }

    /**
     * Re-checks every nullable-bool leaf of a nested value object against the raw submitted value.
     * API Platform coerces a JSON `1`/`"True"` to a real bool before validation runs, so `Assert\Type`
     * passes on the cast value and the boolean type error is never produced. For each `?bool` leaf
     * that was submitted as a non-boolean, appends `<object>.<leaf> => This value should be of type
     * boolean.`. The nullable-bool leaves are derived by reflection from the value-object class so no
     * leaf names are hard-coded.
     *
     * @param array<array<string, mixed>> $errors
     * @param array<string, bool> $existingDetails
     * @param array<string, mixed> $submittedObject
     */
    protected function augmentNestedBoolLeaves(
        array &$errors,
        array &$existingDetails,
        string $propertyName,
        string $valueObjectClass,
        array $submittedObject,
    ): bool {
        $modified = false;

        foreach ($this->resolveNullableBoolLeaves($valueObjectClass) as $leaf) {
            if (!array_key_exists($leaf, $submittedObject) || is_bool($submittedObject[$leaf])) {
                continue;
            }

            $detail = sprintf('%s.%s => %s', $propertyName, $leaf, static::TYPE_BOOLEAN_ERROR_MESSAGE);

            if (isset($existingDetails[$detail])) {
                continue;
            }

            $errors[] = ['detail' => $detail, 'code' => static::ERROR_CODE_VALIDATION, 'status' => Response::HTTP_UNPROCESSABLE_ENTITY];
            $existingDetails[$detail] = true;
            $modified = true;
        }

        return $modified;
    }

    /**
     * Returns the value object's leaf properties typed as a nullable bool (`?bool`).
     *
     * @return array<int, string>
     */
    protected function resolveNullableBoolLeaves(string $valueObjectClass): array
    {
        if (!class_exists($valueObjectClass)) {
            return [];
        }

        $leaves = [];

        foreach ((new ReflectionClass($valueObjectClass))->getProperties() as $property) {
            $type = $property->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === 'bool' && $type->allowsNull()) {
                $leaves[] = $property->getName();
            }
        }

        return $leaves;
    }

    /**
     * Whether the generated value-object class opts into empty-object ({}) 422 synthesis via the
     * schema-driven SYNTHESIZE_MISSING_FIELDS_WHEN_EMPTY marker constant (set from the resource YAML
     * `synthesizeMissingFieldsWhenEmpty` flag). Replaces the former hard-coded property-name list so
     * the augmenter stays ignorant of specific resource field names.
     */
    protected function synthesizesMissingFieldsWhenEmpty(string $valueObjectClass): bool
    {
        return class_exists($valueObjectClass)
            && (new ReflectionClass($valueObjectClass))->hasConstant('SYNTHESIZE_MISSING_FIELDS_WHEN_EMPTY')
            && (new ReflectionClass($valueObjectClass))->getConstant('SYNTHESIZE_MISSING_FIELDS_WHEN_EMPTY') === true;
    }

    /**
     * For each required leaf (NotBlank/NotNull/Email in the active groups) of the value object that
     * was NOT submitted in the present-but-empty parent object, ensures a
     * `<object>.<leaf> => This field is missing.` error exists — relabeling an error the validator
     * already produced, or synthesizing one when the constraint allowed null.
     *
     * @param array<array<string, mixed>> $errors
     * @param array<string, bool> $existingDetails
     * @param array<string, mixed> $submittedObject
     * @param array<string> $groups
     */
    protected function augmentNestedMissingLeaves(
        array &$errors,
        array &$existingDetails,
        string $propertyName,
        string $valueObjectClass,
        array $submittedObject,
        array $groups,
    ): bool {
        $modified = false;

        foreach ($this->resolveRequiredValueObjectLeaves($valueObjectClass, $groups) as $leaf) {
            if (array_key_exists($leaf, $submittedObject)) {
                continue;
            }

            $missingDetail = sprintf('%s.%s => %s', $propertyName, $leaf, static::FIELD_MISSING_MESSAGE);

            if (isset($existingDetails[$missingDetail])) {
                continue;
            }

            // Relabel an existing error for this leaf before synthesizing a new one, so the
            // validator's NotBlank/Email message is replaced rather than duplicated.
            $relabeled = false;

            foreach ($errors as &$error) {
                $detail = $error['detail'] ?? '';

                if (preg_match(sprintf(static::REGEX_NESTED_LEAF_DETAIL_PREFIX_TEMPLATE, preg_quote($propertyName, '/'), preg_quote($leaf, '/')), (string)$detail)) {
                    unset($existingDetails[$detail]);
                    $error['detail'] = $missingDetail;
                    $relabeled = true;

                    break;
                }
            }

            unset($error);

            if (!$relabeled) {
                // Only SYNTHESIZE a missing-field error for an entirely-empty required object.
                // A present-but-partial object, or any nested object that does not opt into
                // empty-object synthesis, is valid input here — synthesizing would wrongly 422 it.
                if ($submittedObject !== [] || !$this->synthesizesMissingFieldsWhenEmpty($valueObjectClass)) {
                    continue;
                }

                $errors[] = ['detail' => $missingDetail, 'code' => static::ERROR_CODE_VALIDATION, 'status' => Response::HTTP_UNPROCESSABLE_ENTITY];
            }

            $existingDetails[$missingDetail] = true;
            $modified = true;
        }

        return $modified;
    }

    /**
     * Returns the resource's nested value-object properties as `propertyName => valueObjectClass`.
     * A nested value object is a property typed as a non-nullable-stripped class under the generated
     * `Generated\Api\Storefront` namespace (the canonical `objectName` objects cascaded via
     * `Assert\Valid`). Properties typed `mixed`/`array` (no class metadata to cascade into) are
     * ignored.
     *
     * @return array<string, string>
     */
    protected function resolveNestedObjectProperties(string $resourceClass): array
    {
        $nestedProperties = [];

        if (!class_exists($resourceClass)) {
            return $nestedProperties;
        }

        foreach ((new ReflectionClass($resourceClass))->getProperties() as $property) {
            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $valueObjectClass = $type->getName();

            if (!str_starts_with($valueObjectClass, static::GENERATED_VALUE_OBJECT_NAMESPACE) || !class_exists($valueObjectClass)) {
                continue;
            }

            $nestedProperties[$property->getName()] = $valueObjectClass;
        }

        return $nestedProperties;
    }

    /**
     * Returns the value object's leaf fields that are required-when-present in the active groups,
     * i.e. carry a NotBlank, NotNull or Email constraint. `Assert\NotBlank(allowNull: true)` (the
     * generated object shape) still counts as required here — the leaf is required when the
     * object is submitted; null-tolerance is what the legacy contract overrides to "field is
     * missing." Leaf order follows declaration order to match the legacy error sequence.
     *
     * @param array<string> $groups
     *
     * @return array<int, string>
     */
    protected function resolveRequiredValueObjectLeaves(string $valueObjectClass, array $groups): array
    {
        $leaves = [];

        if (!class_exists($valueObjectClass)) {
            return $leaves;
        }

        foreach ((new ReflectionClass($valueObjectClass))->getProperties() as $property) {
            foreach ($this->constraintReader->getConstraintsForGroups($valueObjectClass, $property->getName(), $groups) as $constraint) {
                if (
                    $constraint instanceof NotBlank
                    || $constraint instanceof NotNull
                    || $constraint instanceof Email
                ) {
                    $leaves[] = $property->getName();

                    break;
                }
            }
        }

        return $leaves;
    }
}
