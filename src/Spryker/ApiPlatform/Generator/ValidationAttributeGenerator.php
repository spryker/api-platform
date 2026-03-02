<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;

/**
 * Generates #[Assert\...] validation attributes for resource properties with group support.
 *
 * Transforms validation constraints from schema into Symfony validation attributes, handling
 * operation-specific validation groups, constraint deduplication, and Optional constraint unwrapping.
 *
 * Input validation schema excerpt:
 * ```php
 * [
 *     'post' => [
 *         'email' => [
 *             ['NotBlank' => ['message' => 'Email is required']],
 *             ['Email' => ['message' => 'Invalid email format']],
 *         ],
 *     ],
 *     'patch' => [
 *         'email' => [
 *             ['Optional' => ['constraints' => [
 *                 ['Email' => ['message' => 'Invalid email format']],
 *             ]]],
 *         ],
 *     ],
 * ]
 * ```
 *
 * Generated output for property with Post and Patch operations:
 * ```php
 * #[Assert\NotBlank(message: 'Email is required', groups: ['customers:create'])]
 * #[Assert\Email(message: 'Invalid email format', groups: ['customers:create', 'customers:update'])]
 * ```
 *
 * Key behaviors:
 * - Maps operations to validation groups (Post → 'customers:create', Patch → 'customers:update')
 * - Deduplicates same constraints across groups (merges groups when constraint is identical)
 * - Unwraps Optional constraints and skips NotBlank inside Optional wrappers
 * - Handles fully qualified class name constraints with alias resolution
 */
class ValidationAttributeGenerator
{
    public function __construct(
        protected readonly ValidationGroupMapperInterface $validationGroupMapper,
        protected readonly ConstraintFormatter $constraintFormatter,
        protected readonly FqcnConstraintResolver $fqcnConstraintResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     *
     * @return array<string>
     */
    public function generate(array $validationSchema, array $operations, string $propertyName, string $resourceName): array
    {
        $constraintsWithGroups = [];

        foreach ($operations as $operationType => $operation) {
            $httpMethod = strtolower($operationType);

            if (!isset($validationSchema[$httpMethod][$propertyName])) {
                continue;
            }

            $group = $this->validationGroupMapper->mapOperationToGroup($operationType, $resourceName);
            $constraints = $validationSchema[$httpMethod][$propertyName];

            foreach ($constraints as $constraint) {
                if ($this->isOptionalConstraint($constraint)) {
                    $nestedConstraints = $this->extractNestedConstraintsFromOptional($constraint);

                    foreach ($nestedConstraints as $nestedConstraint) {
                        if ($this->shouldSkipConstraintForOptionalField($nestedConstraint)) {
                            continue;
                        }

                        $constraintsWithGroups[] = [
                            'constraint' => $nestedConstraint,
                            'group' => $group,
                        ];
                    }

                    continue;
                }

                $constraintsWithGroups[] = [
                    'constraint' => $constraint,
                    'group' => $group,
                ];
            }
        }

        $deduplicatedConstraints = $this->deduplicateConstraintsByGroups($constraintsWithGroups);

        $attributes = [];

        foreach ($deduplicatedConstraints as $constraintData) {
            $attributes[] = $this->constraintFormatter->generateConstraintAttribute($constraintData['constraint'], $constraintData['groups']);
        }

        return $attributes;
    }

    /**
     * @param array<array{constraint: mixed, group: string}> $constraintsWithGroups
     *
     * @return array<array{constraint: mixed, groups: array<string>}>
     */
    protected function deduplicateConstraintsByGroups(array $constraintsWithGroups): array
    {
        $groupedByConstraint = [];

        foreach ($constraintsWithGroups as $item) {
            $constraint = $item['constraint'];
            $group = $item['group'];

            $key = $this->generateConstraintKey($constraint);

            if (!isset($groupedByConstraint[$key])) {
                $groupedByConstraint[$key] = [
                    'constraint' => $constraint,
                    'groups' => [],
                ];
            }

            $groupedByConstraint[$key]['groups'][] = $group;
        }

        foreach ($groupedByConstraint as $key => $data) {
            $groupedByConstraint[$key]['groups'] = array_values(array_unique($data['groups']));
            sort($groupedByConstraint[$key]['groups']);
        }

        return array_values($groupedByConstraint);
    }

    protected function generateConstraintKey(mixed $constraint): string
    {
        if (is_string($constraint)) {
            if ($this->isFqcn($constraint)) {
                return $this->normalizeFqcn($constraint);
            }

            return $constraint;
        }

        if (!is_array($constraint)) {
            return 'unknown_' . md5(serialize($constraint));
        }

        $constraintName = (string)array_key_first($constraint);
        $normalizedName = $this->isFqcn($constraintName) ? $this->normalizeFqcn($constraintName) : $constraintName;
        $options = $constraint[$constraintName];

        if (!is_array($options)) {
            return $normalizedName;
        }

        return $normalizedName . '_' . md5(serialize($options));
    }

    protected function isOptionalConstraint(mixed $constraint): bool
    {
        if (!is_array($constraint)) {
            return false;
        }

        return isset($constraint['Optional']);
    }

    /**
     * @param array<mixed> $constraint
     *
     * @return array<mixed>
     */
    protected function extractNestedConstraintsFromOptional(array $constraint): array
    {
        if (!isset($constraint['Optional']['constraints'])) {
            return [];
        }

        return $constraint['Optional']['constraints'];
    }

    protected function shouldSkipConstraintForOptionalField(mixed $constraint): bool
    {
        if (is_string($constraint) && $constraint === 'NotBlank') {
            return true;
        }

        if (is_array($constraint) && isset($constraint['NotBlank'])) {
            return true;
        }

        return false;
    }

    protected function normalizeFqcn(string $fqcn): string
    {
        return ltrim($fqcn, '\\');
    }

    protected function isFqcn(string $constraintName): bool
    {
        return str_contains($constraintName, '\\');
    }
}
