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
 * Supports composite constraints (All, Sequentially, Composite) with nested constraint arrays.
 */
class ConstraintFormatter
{
    /**
     * @var array<string>
     */
    protected const array COMPOSITE_CONSTRAINTS_WITH_CONSTRAINTS_PARAMETER = [
        'All',
        'Sequentially',
        'Composite',
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

    /**
     * @param array<string> $groups
     */
    public function generateConstraintAttribute(mixed $constraint, array $groups): string
    {
        $groupsString = implode("', '", $groups);

        if (is_string($constraint)) {
            if ($this->isFqcn($constraint)) {
                $normalized = $this->normalizeFqcn($constraint);
                $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraint)['shortName'];

                return sprintf("#[%s(groups: ['%s'])]", $alias, $groupsString);
            }

            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraint, $groupsString);
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
                return sprintf("#[%s(groups: ['%s'])]", $alias, $groupsString);
            }

            $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

            if ($formattedOptions === '') {
                return sprintf("#[%s(groups: ['%s'])]", $alias, $groupsString);
            }

            return sprintf("#[%s(%s, groups: ['%s'])]", $alias, $formattedOptions, $groupsString);
        }

        if (!is_array($options)) {
            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraintName, $groupsString);
        }

        $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

        if ($formattedOptions === '') {
            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraintName, $groupsString);
        }

        return sprintf("#[Assert\\%s(%s, groups: ['%s'])]", $constraintName, $formattedOptions, $groupsString);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function formatConstraintOptions(array $options, string $constraintName = ''): string
    {
        $parts = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                if ($key === 'constraints' && in_array($constraintName, static::COMPOSITE_CONSTRAINTS_WITH_CONSTRAINTS_PARAMETER, true)) {
                    $formattedConstraints = $this->formatNestedConstraints($value);
                    $parts[] = sprintf('%s: %s', $key, $formattedConstraints);

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
     */
    protected function formatNestedConstraints(array $constraints): string
    {
        $formattedConstraints = [];

        foreach ($constraints as $constraint) {
            $formattedConstraint = $this->formatSingleNestedConstraint($constraint);

            if ($formattedConstraint !== '') {
                $formattedConstraints[] = $formattedConstraint;
            }
        }

        return '[' . implode(', ', $formattedConstraints) . ']';
    }

    protected function formatSingleNestedConstraint(mixed $constraint): string
    {
        if (is_string($constraint)) {
            if ($this->isFqcn($constraint)) {
                $normalized = $this->normalizeFqcn($constraint);
                $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraint)['shortName'];

                return sprintf('new %s()', $alias);
            }

            return sprintf('new Assert\%s()', $constraint);
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
                return sprintf('new %s()', $alias);
            }

            $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

            if ($formattedOptions === '') {
                return sprintf('new %s()', $alias);
            }

            return sprintf('new %s(%s)', $alias, $formattedOptions);
        }

        if (!is_array($options)) {
            return sprintf('new Assert\%s()', $constraintName);
        }

        $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

        if ($formattedOptions === '') {
            return sprintf('new Assert\%s()', $constraintName);
        }

        return sprintf('new Assert\%s(%s)', $constraintName, $formattedOptions);
    }

    /**
     * @param array<mixed> $array
     */
    protected function formatArrayValue(array $array): string
    {
        $items = [];

        foreach ($array as $value) {
            if (is_string($value)) {
                $items[] = sprintf("'%s'", addslashes($value));

                continue;
            }

            if (is_array($value)) {
                $items[] = $this->formatArrayValue($value);

                continue;
            }

            $items[] = (string)$value;
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
