<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * Formats validation constraints into PHP 8 attribute syntax for Symfony validation.
 *
 * Converts constraint definitions from validation schema into properly formatted
 * PHP attribute strings, handling options, groups, nested constraints, and fully qualified class name constraints.
 *
 * Input constraint examples:
 * ```php
 * // Simple constraint
 * 'NotBlank'
 *
 * // Constraint with options
 * ['Email' => ['message' => 'Invalid email']]
 *
 * // Fully qualified class name constraint
 * ['Spryker\Shared\Validator\Constraints\UniqueEmail' => ['message' => 'Email exists']]
 *
 * // Composite constraint with nested constraints
 * ['All' => ['constraints' => [['Email' => [...]], ['NotBlank' => [...]]]]]
 * ```
 *
 * Generated outputs with groups:
 * ```php
 * #[Assert\NotBlank(groups: ['customers:create'])]
 * #[Assert\Email(message: 'Invalid email', groups: ['customers:create'])]
 * #[SprykerUniqueEmail(message: 'Email exists', groups: ['customers:create'])]
 * #[Assert\All(constraints: [new Assert\Email(...), new Assert\NotBlank(...)], groups: ['customers:create'])]
 * ```
 *
 * Handles alias resolution for fully qualified class name constraints to prevent naming conflicts.
 * Supports composite constraints (All, AtLeastOneOf, Sequentially, Composite) with nested constraint arrays.
 */
class ConstraintFormatter
{
    /**
     * @var array<string>
     */
    protected const array COMPOSITE_CONSTRAINTS_WITH_CONSTRAINTS_PARAMETER = [
        'All',
        'AtLeastOneOf',
        'Sequentially',
        'Composite',
        'When',
    ];

    /**
     * @var array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    protected array $fqcnConstraintMap = [];

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string}> $fqcnConstraintMap
     */
    public function setFqcnConstraintMap(array $fqcnConstraintMap): void
    {
        $this->fqcnConstraintMap = $fqcnConstraintMap;
    }

    protected const string PAYLOAD_OPTION_KEY = 'payload';

    protected const string OPTIONAL_PAYLOAD_KEY = 'source';

    protected const string OPTIONAL_PAYLOAD_VALUE = 'optional';

    protected const string OPTIONAL_PAYLOAD_SUFFIX = ", payload: ['" . self::OPTIONAL_PAYLOAD_KEY . "' => '" . self::OPTIONAL_PAYLOAD_VALUE . "']";

    /**
     * @param array<string> $groups
     */
    public function generateConstraintAttribute(mixed $constraint, array $groups): string
    {
        return $this->formatConstraintAttribute($constraint, $groups, '');
    }

    /**
     * @param array<string> $groups
     */
    public function generateConstraintAttributeWithOptionalPayload(mixed $constraint, array $groups): string
    {
        return $this->formatConstraintAttribute($constraint, $groups, static::OPTIONAL_PAYLOAD_SUFFIX);
    }

    /**
     * @param array<string> $groups
     */
    protected function formatConstraintAttribute(mixed $constraint, array $groups, string $payloadSuffix): string
    {
        $groupsString = implode("', '", $groups);

        if (is_string($constraint)) {
            if ($this->isFqcn($constraint)) {
                $normalized = $this->normalizeFqcn($constraint);
                $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraint)['shortName'];

                return sprintf("#[%s(groups: ['%s']%s)]", $alias, $groupsString, $payloadSuffix);
            }

            return sprintf("#[Assert\\%s(groups: ['%s']%s)]", $constraint, $groupsString, $payloadSuffix);
        }

        if (!is_array($constraint)) {
            return '';
        }

        $constraintName = (string)array_key_first($constraint);
        $options = $constraint[$constraintName];

        if ($payloadSuffix !== '' && is_array($options) && array_key_exists(static::PAYLOAD_OPTION_KEY, $options)) {
            $options = $this->mergeOptionalPayload($options);
            $payloadSuffix = '';
        }

        if ($this->isFqcn($constraintName)) {
            $normalized = $this->normalizeFqcn($constraintName);
            $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraintName)['shortName'];

            if (!is_array($options)) {
                return sprintf("#[%s(groups: ['%s']%s)]", $alias, $groupsString, $payloadSuffix);
            }

            $formattedOptions = $this->formatConstraintOptions($options, $constraintName, $groups);

            if ($formattedOptions === '') {
                return sprintf("#[%s(groups: ['%s']%s)]", $alias, $groupsString, $payloadSuffix);
            }

            return sprintf("#[%s(%s, groups: ['%s']%s)]", $alias, $formattedOptions, $groupsString, $payloadSuffix);
        }

        if (!is_array($options)) {
            return sprintf("#[Assert\\%s(groups: ['%s']%s)]", $constraintName, $groupsString, $payloadSuffix);
        }

        $formattedOptions = $this->formatConstraintOptions($options, $constraintName, $groups);

        if ($formattedOptions === '') {
            return sprintf("#[Assert\\%s(groups: ['%s']%s)]", $constraintName, $groupsString, $payloadSuffix);
        }

        return sprintf("#[Assert\\%s(%s, groups: ['%s']%s)]", $constraintName, $formattedOptions, $groupsString, $payloadSuffix);
    }

    /**
     * A constraint declaring its own `payload` would otherwise get a second `payload:` named
     * argument from the suffix, which is a fatal at class load.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function mergeOptionalPayload(array $options): array
    {
        $payload = is_array($options[static::PAYLOAD_OPTION_KEY]) ? $options[static::PAYLOAD_OPTION_KEY] : [];
        $payload[static::OPTIONAL_PAYLOAD_KEY] = static::OPTIONAL_PAYLOAD_VALUE;
        $options[static::PAYLOAD_OPTION_KEY] = $payload;

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string> $groups
     */
    protected function formatConstraintOptions(array $options, string $constraintName = '', array $groups = []): string
    {
        $parts = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                if ($key === 'constraints' && in_array($constraintName, static::COMPOSITE_CONSTRAINTS_WITH_CONSTRAINTS_PARAMETER, true)) {
                    $formattedConstraints = $this->formatNestedConstraints($value, $groups);
                    $parts[] = sprintf('%s: %s', $key, $formattedConstraints);

                    continue;
                }

                if ($key === 'fields' && $constraintName === 'Collection') {
                    $formattedFields = $this->formatCollectionFields($value, $groups);
                    $parts[] = sprintf('%s: %s', $key, $formattedFields);

                    continue;
                }

                $formattedArray = $this->formatArrayValue($value);
                $parts[] = sprintf('%s: %s', $key, $formattedArray);

                continue;
            }

            if (is_string($value)) {
                $escapedValue = addslashes($value);
                $parts[] = sprintf("%s: '%s'", $key, $escapedValue);

                continue;
            }

            if (is_bool($value)) {
                $parts[] = sprintf('%s: %s', $key, $value ? 'true' : 'false');

                continue;
            }

            $parts[] = sprintf('%s: %s', $key, $value);
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<mixed> $constraints
     * @param array<string> $groups
     */
    protected function formatNestedConstraints(array $constraints, array $groups = []): string
    {
        $formattedConstraints = [];

        foreach ($constraints as $constraint) {
            $formattedConstraint = $this->formatSingleNestedConstraint($constraint, $groups);

            if ($formattedConstraint !== '') {
                $formattedConstraints[] = $formattedConstraint;
            }
        }

        return '[' . implode(', ', $formattedConstraints) . ']';
    }

    /**
     * @param array<string> $groups
     */
    protected function formatSingleNestedConstraint(mixed $constraint, array $groups = []): string
    {
        $groupsArgument = $this->formatGroupsArgument($groups);

        if (is_string($constraint)) {
            if ($this->isFqcn($constraint)) {
                $normalized = $this->normalizeFqcn($constraint);
                $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraint)['shortName'];

                return sprintf('new %s(%s)', $alias, $groupsArgument);
            }

            return sprintf('new Assert\%s(%s)', $constraint, $groupsArgument);
        }

        if (!is_array($constraint)) {
            return '';
        }

        $constraintName = (string)array_key_first($constraint);
        $options = $constraint[$constraintName];

        if ($this->isFqcn($constraintName)) {
            $normalized = $this->normalizeFqcn($constraintName);
            $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraintName)['shortName'];

            if (!is_array($options)) {
                return sprintf('new %s(%s)', $alias, $groupsArgument);
            }

            $formattedOptions = $this->formatConstraintOptions($options, $constraintName, $groups);

            if ($formattedOptions === '') {
                return sprintf('new %s(%s)', $alias, $groupsArgument);
            }

            return sprintf('new %s(%s%s)', $alias, $formattedOptions, $this->appendGroupsArgument($groups));
        }

        if (!is_array($options)) {
            return sprintf('new Assert\%s(%s)', $constraintName, $groupsArgument);
        }

        $formattedOptions = $this->formatConstraintOptions($options, $constraintName, $groups);

        if ($formattedOptions === '') {
            return sprintf('new Assert\%s(%s)', $constraintName, $groupsArgument);
        }

        return sprintf('new Assert\%s(%s%s)', $constraintName, $formattedOptions, $this->appendGroupsArgument($groups));
    }

    /**
     * @param array<string, array<mixed>> $fields
     * @param array<string> $groups
     */
    protected function formatCollectionFields(array $fields, array $groups = []): string
    {
        $fieldParts = [];

        foreach ($fields as $fieldName => $constraints) {
            $fieldConstraints = $this->formatFieldConstraints($constraints, $groups);
            $fieldParts[] = sprintf("'%s' => %s", addslashes($fieldName), $fieldConstraints);
        }

        return '[' . implode(', ', $fieldParts) . ']';
    }

    /**
     * @param array<mixed> $constraints
     * @param array<string> $groups
     */
    protected function formatFieldConstraints(array $constraints, array $groups = []): string
    {
        if (count($constraints) === 1) {
            $constraint = $constraints[0];

            if (is_array($constraint) && (isset($constraint['Optional']) || isset($constraint['Required']))) {
                $wrapperName = isset($constraint['Optional']) ? 'Optional' : 'Required';
                $innerConfig = $constraint[$wrapperName];
                $innerConstraints = $innerConfig['constraints'] ?? [];
                $formattedInner = $this->formatNestedConstraints($innerConstraints, $groups);

                return sprintf('new Assert\%s(%s)', $wrapperName, $formattedInner);
            }
        }

        return $this->formatNestedConstraints($constraints, $groups);
    }

    /**
     * @param array<string> $groups
     */
    protected function formatGroupsArgument(array $groups): string
    {
        if ($groups === []) {
            return '';
        }

        $groupsString = implode("', '", $groups);

        return sprintf("groups: ['%s']", $groupsString);
    }

    /**
     * @param array<string> $groups
     */
    protected function appendGroupsArgument(array $groups): string
    {
        if ($groups === []) {
            return '';
        }

        $groupsString = implode("', '", $groups);

        return sprintf(", groups: ['%s']", $groupsString);
    }

    /**
     * @param array<mixed> $array
     */
    protected function formatArrayValue(array $array): string
    {
        $isList = array_is_list($array);
        $items = [];

        foreach ($array as $key => $value) {
            $prefix = $isList ? '' : sprintf("'%s' => ", addslashes((string)$key));

            if (is_string($value)) {
                $items[] = $prefix . sprintf("'%s'", addslashes($value));

                continue;
            }

            if (is_array($value)) {
                $items[] = $prefix . $this->formatArrayValue($value);

                continue;
            }

            $items[] = $prefix . (string)$value;
        }

        return '[' . implode(', ', $items) . ']';
    }

    protected function normalizeFqcn(string $fqcn): string
    {
        return ltrim($fqcn, '\\');
    }

    protected function isFqcn(string $constraintName): bool
    {
        return str_contains($constraintName, '\\');
    }

    /**
     * @return array{namespace: string, shortName: string, namespaceParts: array<string>}
     */
    protected function parseConstraintFqcn(string $fqcn): array
    {
        $normalized = $this->normalizeFqcn($fqcn);
        $parts = explode('\\', $normalized);
        $shortName = array_pop($parts);

        return [
            'namespace' => implode('\\', $parts),
            'shortName' => $shortName,
            'namespaceParts' => $parts,
        ];
    }
}
