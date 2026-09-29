<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

use ReflectionNamedType;
use ReflectionProperty;
use Spryker\ApiPlatform\Validation\Trait\ValidationMessageTranslationTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AbstractComparison;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Restores the validation errors the legacy REST API produced for strings submitted to numeric
 * properties, which API Platform coerces or rejects before the validator sees the raw value.
 *
 * A pure transformer: it takes the already-decoded errors and returns the augmented set. All HTTP
 * request/response handling stays in the caller.
 */
class NumericValidationErrorAugmenter
{
    use ValidationMessageTranslationTrait;

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const string MESSAGE_TEMPLATE_TYPE = 'This value should be of type {{ type }}.';

    protected const string MESSAGE_TEMPLATE_GREATER_THAN = 'This value should be greater than {{ compared_value }}.';

    protected const string TYPE_NAME_INTEGER = 'integer';

    protected const string TYPE_NAME_NUMERIC = 'numeric';

    protected const string COMPARED_VALUE_ZERO = '0';

    /**
     * Captures the field name of a detail whose only violation is "This value should not be blank.".
     */
    protected const string REGEX_NOT_BLANK_ONLY_FIELD = '/^(\w+) => This value should not be blank\.$/';

    /**
     * Captures the field name of a detail that carries a violation OTHER than the "not blank" one.
     */
    protected const string REGEX_NON_NOT_BLANK_FIELD = '/^(\w+) => (?!This value should not be blank\.$)/';

    public function __construct(
        protected ValidationConstraintReader $constraintReader,
        protected TranslatorInterface $translator,
    ) {
    }

    /**
     * Detects numeric-typed properties on the resource class that have NotBlank errors
     * but are missing Type and comparison constraint errors. This happens when API Platform
     * converts empty strings to null for typed properties (e.g. ?int) before validation runs.
     * The old REST API validated raw strings, so all constraints fired.
     *
     * @param array<string> $groups
     * @param array<int, array<string, mixed>> $errors
     *
     * @return array<int, array<string, mixed>>
     */
    public function augmentEmptyStringValues(string $resourceClass, array $groups, array $errors): array
    {
        if (!class_exists($resourceClass)) {
            return $errors;
        }

        $existingDetails = [];
        $fieldsWithNotBlankOnly = [];

        foreach ($errors as $error) {
            $detail = $error['detail'] ?? '';
            $existingDetails[$detail] = true;

            if (preg_match(static::REGEX_NOT_BLANK_ONLY_FIELD, $detail, $matches)) {
                $fieldsWithNotBlankOnly[$matches[1]] = true;
            }
        }

        // Remove fields that already have additional errors beyond NotBlank
        foreach ($errors as $error) {
            $detail = $error['detail'] ?? '';

            if (preg_match(static::REGEX_NON_NOT_BLANK_FIELD, $detail, $matches)) {
                unset($fieldsWithNotBlankOnly[$matches[1]]);
            }
        }

        foreach (array_keys($fieldsWithNotBlankOnly) as $fieldName) {
            if (!$this->isNumericProperty($resourceClass, $fieldName)) {
                continue;
            }

            $messages = $this->buildSyntheticErrorsForEmptyNumericProperty($resourceClass, $fieldName, $groups);

            if ($messages === []) {
                $messages = $this->buildFallbackNumericMessages();
            }

            foreach ($messages as $errorMessage) {
                $detail = sprintf('%s => %s', $fieldName, $errorMessage);

                if (isset($existingDetails[$detail])) {
                    continue;
                }

                $errors[] = [
                    'detail' => $detail,
                    'code' => static::ERROR_CODE_VALIDATION,
                    'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                ];

                $existingDetails[$detail] = true;
            }
        }

        return $errors;
    }

    /**
     * Restores validation errors that the legacy REST API produced for non-empty string values
     * submitted to typed integer fields. Two cases arise:
     *
     * 1. Numeric string (e.g. "-2"): AP coerces it to int before validation, so the Type constraint
     *    passes on the already-cast value. "This value should be of type integer." is missing and
     *    must be prepended to the existing comparison-constraint errors.
     *
     * 2. Non-numeric string (e.g. "test"): AP's PropertyAccessor cannot coerce the value and throws,
     *    which the exception handler converts to a single "type numeric" error — bypassing Symfony
     *    Validator entirely. "type numeric" must be replaced by "type integer", and any comparison
     *    constraints that would have fired against the raw string (using PHP 8 semantics) are added.
     *
     * @param array<string, mixed> $rawAttributes
     * @param array<string> $groups
     * @param array<int, array<string, mixed>> $errors
     *
     * @return array<int, array<string, mixed>>
     */
    public function augmentStringNumericValues(string $resourceClass, array $rawAttributes, array $groups, array $errors): array
    {
        if (!class_exists($resourceClass)) {
            return $errors;
        }

        foreach (array_keys($rawAttributes) as $fieldName) {
            $rawValue = $rawAttributes[$fieldName];

            if (!$this->isStringOrIntegerOverflowValue($rawValue)) {
                continue;
            }

            if (!$this->isNumericProperty($resourceClass, $fieldName)) {
                continue;
            }

            $typeNumericDetail = sprintf('%s => %s', $fieldName, $this->buildFallbackNumericMessages()[0]);
            $typeIntegerDetail = sprintf('%s => %s', $fieldName, $this->translateTypeMessage(static::TYPE_NAME_INTEGER));

            $existingDetails = array_column($errors, 'detail');
            $hasTypeNumeric = in_array($typeNumericDetail, $existingDetails, true);
            $hasTypeInteger = in_array($typeIntegerDetail, $existingDetails, true);

            if ($hasTypeInteger) {
                continue;
            }

            if ($hasTypeNumeric) {
                $this->replaceTypeNumericWithTypeIntegerError($errors, $resourceClass, $fieldName, (string)$rawValue, $groups, $typeNumericDetail, $typeIntegerDetail);

                continue;
            }

            if (is_string($rawValue) && $rawValue !== '') {
                $this->prependTypeIntegerErrorForNumericString($errors, $resourceClass, $fieldName, $rawValue, $groups);
            }
        }

        return $errors;
    }

    /**
     * Returns true for non-empty strings and for floats that result from JSON integer overflow
     * (e.g. 99999999999999999999 decoded as float). PropertyAccessor cannot assign a float to ?int,
     * so the exception handler produces "type numeric" — both cases need the same replacement logic.
     */
    protected function isStringOrIntegerOverflowValue(mixed $value): bool
    {
        return (is_string($value) && $value !== '') || is_float($value);
    }

    /**
     * Replaces the generic "type numeric" error with "type integer" and appends any
     * comparison constraint violations for fields where the Type constraint declares integer.
     *
     * @param array<array<string, mixed>> $errors
     * @param array<string> $groups
     */
    protected function replaceTypeNumericWithTypeIntegerError(
        array &$errors,
        string $resourceClass,
        string $fieldName,
        string $rawValue,
        array $groups,
        string $typeNumericDetail,
        string $typeIntegerDetail,
    ): void {
        $declaredType = $this->getTypeConstraintTypeName($resourceClass, $fieldName, $groups);

        if ($declaredType !== 'integer' && $declaredType !== 'int') {
            return;
        }

        $errors = array_values(array_filter(
            $errors,
            static fn (array $e): bool => ($e['detail'] ?? '') !== $typeNumericDetail,
        ));

        array_unshift($errors, [
            'detail' => $typeIntegerDetail,
            'code' => static::ERROR_CODE_VALIDATION,
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
        ]);

        $this->appendComparisonConstraintErrors($errors, $resourceClass, $fieldName, $rawValue, $groups);
    }

    /**
     * Prepends a "type integer" error when the raw numeric string (e.g. "-2") fails the
     * Assert\Type constraint declared on the property. API Platform silently coerced the value
     * before validation, so the constraint never fired — this restores the legacy behavior.
     *
     * @param array<array<string, mixed>> $errors
     * @param array<string> $groups
     */
    protected function prependTypeIntegerErrorForNumericString(
        array &$errors,
        string $resourceClass,
        string $fieldName,
        string $rawValue,
        array $groups,
    ): void {
        $typeError = $this->buildTypeErrorForRawStringValue($resourceClass, $fieldName, $rawValue, $groups);

        if ($typeError === null) {
            return;
        }

        array_unshift($errors, [
            'detail' => sprintf('%s => %s', $fieldName, $typeError),
            'code' => static::ERROR_CODE_VALIDATION,
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
        ]);
    }

    /**
     * @param array<array<string, mixed>> $errors
     * @param array<string> $groups
     */
    protected function appendComparisonConstraintErrors(array &$errors, string $resourceClass, string $fieldName, string $rawValue, array $groups): void
    {
        foreach ($this->evaluateComparisonConstraints($resourceClass, $fieldName, $rawValue, $groups) as $errorDetail) {
            $errors[] = [
                'detail' => sprintf('%s => %s', $fieldName, $errorDetail),
                'code' => static::ERROR_CODE_VALIDATION,
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            ];
        }
    }

    /**
     * @param array<string> $groups
     */
    protected function buildTypeErrorForRawStringValue(string $resourceClass, string $fieldName, string $rawValue, array $groups): ?string
    {
        $constraint = $this->getTypeConstraintInstance($resourceClass, $fieldName, $groups);

        // @phpstan-ignore isset.property (kept for BC/defensiveness even though $message is currently non-nullable upstream)
        if ($constraint === null || !isset($constraint->type, $constraint->message)) {
            return null;
        }

        $type = is_array($constraint->type) ? $constraint->type[0] : (string)$constraint->type;

        $passes = match ($type) {
            'integer', 'int' => false,
            'numeric' => is_numeric($rawValue),
            'float', 'double' => false,
            'string' => true,
            'bool', 'boolean' => false,
            default => true,
        };

        if ($passes) {
            return null;
        }

        return strtr((string)$constraint->message, ['{{ type }}' => $type]);
    }

    /**
     * Evaluates GreaterThan and LessThan constraints against the raw submitted string value
     * using PHP 8 comparison semantics (non-numeric strings are compared as strings after
     * converting the comparand to string). Returns the message for each failing constraint.
     *
     * @param array<string> $groups
     *
     * @return array<string>
     */
    protected function evaluateComparisonConstraints(string $resourceClass, string $fieldName, string $rawValue, array $groups): array
    {
        $errors = [];

        foreach ($this->constraintReader->getConstraintsForGroups($resourceClass, $fieldName, $groups) as $constraint) {
            if (!$constraint instanceof GreaterThan && !$constraint instanceof LessThan) {
                continue;
            }

            if (!isset($constraint->value, $constraint->message)) {
                continue;
            }

            $comparand = $constraint->value;
            $violated = $constraint instanceof GreaterThan
                ? !($rawValue > $comparand)
                : !($rawValue < $comparand);

            if (!$violated) {
                continue;
            }

            $comparandString = is_scalar($constraint->value) ? (string)$constraint->value : '';
            $msg = strtr((string)$constraint->message, ['{{ compared_value }}' => $comparandString]);

            if (!in_array($msg, $errors, true)) {
                $errors[] = $msg;
            }
        }

        return $errors;
    }

    /**
     * @param array<string> $groups
     */
    protected function getTypeConstraintTypeName(string $resourceClass, string $fieldName, array $groups): ?string
    {
        $constraint = $this->getTypeConstraintInstance($resourceClass, $fieldName, $groups);

        if ($constraint === null || !isset($constraint->type)) {
            return null;
        }

        return is_array($constraint->type) ? $constraint->type[0] : (string)$constraint->type;
    }

    /**
     * @param array<string> $groups
     */
    protected function getTypeConstraintInstance(string $resourceClass, string $fieldName, array $groups): ?Type
    {
        foreach ($this->constraintReader->getConstraintsForGroups($resourceClass, $fieldName, $groups) as $constraint) {
            if ($constraint instanceof Type) {
                return $constraint;
            }
        }

        return null;
    }

    protected function isNumericProperty(string $resourceClass, string $propertyName): bool
    {
        if (!property_exists($resourceClass, $propertyName)) {
            return false;
        }

        /** @phpstan-var class-string $resourceClass */
        $type = (new ReflectionProperty($resourceClass, $propertyName))->getType();

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return in_array($type->getName(), ['int', 'float'], true);
    }

    /**
     * @param array<string> $groups
     *
     * @return array<int, string>
     */
    protected function buildSyntheticErrorsForEmptyNumericProperty(string $resourceClass, string $fieldName, array $groups): array
    {
        $errors = [];

        foreach ($this->constraintReader->getConstraintsForGroups($resourceClass, $fieldName, $groups) as $constraint) {
            $message = $this->renderConstraintMessage($constraint);

            if ($message !== null) {
                $errors[] = $message;
            }
        }

        return $errors;
    }

    protected function renderConstraintMessage(Constraint $constraint): ?string
    {
        if ($constraint instanceof Type) {
            $types = is_array($constraint->type) ? implode('|', $constraint->type) : (string)$constraint->type;

            return $this->translateValidationMessage($constraint->message, ['{{ type }}' => $types]);
        }

        if ($constraint instanceof AbstractComparison) {
            return $this->translateValidationMessage($constraint->message, [
                '{{ compared_value }}' => $this->formatConstraintValue($constraint->value),
            ]);
        }

        if ($constraint instanceof Range) {
            return $this->translateValidationMessage($constraint->notInRangeMessage, [
                '{{ min }}' => $this->formatConstraintValue($constraint->min),
                '{{ max }}' => $this->formatConstraintValue($constraint->max),
            ]);
        }

        return null;
    }

    /**
     * What an empty string sent to a numeric property reports when the property declares no Type or
     * comparison constraint of its own to render a message from.
     *
     * @return array<string>
     */
    protected function buildFallbackNumericMessages(): array
    {
        return [
            $this->translateTypeMessage(static::TYPE_NAME_NUMERIC),
            $this->translateValidationMessage(
                static::MESSAGE_TEMPLATE_GREATER_THAN,
                ['{{ compared_value }}' => static::COMPARED_VALUE_ZERO],
            ),
        ];
    }

    protected function translateTypeMessage(string $typeName): string
    {
        return $this->translateValidationMessage(static::MESSAGE_TEMPLATE_TYPE, ['{{ type }}' => $typeName]);
    }

    protected function formatConstraintValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }

    protected function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }
}
