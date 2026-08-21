<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * Resolves aliases for fully qualified class name validation constraints to prevent naming conflicts.
 *
 * Analyzes all fully qualified class name constraints in validation schema and generates unique aliases
 * when multiple constraints share the same short name from different namespaces.
 *
 * Alias resolution strategy:
 * 1. Collect all fully qualified class name constraints from validation schema
 * 2. Group by short name to detect conflicts
 * 3. For conflicts, group by vendor namespace
 * 4. Generate aliases using vendor prefix and disambiguating namespace parts
 *
 * Example scenarios:
 *
 * Single vendor, single constraint:
 * ```php
 * Input: Spryker\Shared\Validator\Constraints\UniqueEmail
 * Output: SprykerUniqueEmail
 * ```
 *
 * Multiple vendors, same short name:
 * ```php
 * Input: Spryker\Shared\Validator\Constraints\Range
 *         Acme\Validator\Constraints\Range
 * Output: SprykerValidatorRange (using namespaceParts[2])
 *         AcmeValidatorRange
 * ```
 *
 * Symfony constraints are never aliased:
 * ```php
 * Input: Symfony\Component\Validator\Constraints\Email
 * Output: Email (no alias, uses Assert\Email)
 * ```
 *
 * Output format:
 * ```php
 * [
 *     'Spryker\Shared\Validator\Constraints\UniqueEmail' => [
 *         'fqcn' => 'Spryker\Shared\Validator\Constraints\UniqueEmail',
 *         'shortName' => 'UniqueEmail',
 *         'alias' => 'SprykerUniqueEmail',
 *     ],
 * ]
 * ```
 */
class FqcnConstraintResolver
{
    /**
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     * @param array<string, mixed> $properties
     *
     * @return array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    public function collectFqcnConstraints(array $validationSchema, array $operations, array $properties): array
    {
        $fqcnConstraints = [];

        foreach ($operations as $operationType => $operation) {
            $httpMethod = strtolower($operationType);

            if (!isset($validationSchema[$httpMethod])) {
                continue;
            }

            foreach ($validationSchema[$httpMethod] as $propertyName => $constraints) {
                foreach ($constraints as $constraint) {
                    $this->extractFqcnFromConstraint($constraint, $fqcnConstraints);
                }
            }
        }

        return $this->resolveFqcnConstraintAliases($fqcnConstraints);
    }

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     */
    protected function extractFqcnFromConstraint(mixed $constraint, array &$fqcnConstraints): void
    {
        if (is_string($constraint) && $this->isFqcn($constraint)) {
            $parsed = $this->parseConstraintFqcn($constraint);
            $normalized = $this->normalizeFqcn($constraint);

            if (!isset($fqcnConstraints[$normalized])) {
                $fqcnConstraints[$normalized] = [
                    'fqcn' => $normalized,
                    'shortName' => $parsed['shortName'],
                    'alias' => $parsed['shortName'],
                    'namespaceParts' => $parsed['namespaceParts'],
                ];
            }

            return;
        }

        if (!is_array($constraint)) {
            return;
        }

        $constraintName = (string)array_key_first($constraint);

        if ($this->isFqcn($constraintName)) {
            $parsed = $this->parseConstraintFqcn($constraintName);
            $normalized = $this->normalizeFqcn($constraintName);

            if (!isset($fqcnConstraints[$normalized])) {
                $fqcnConstraints[$normalized] = [
                    'fqcn' => $normalized,
                    'shortName' => $parsed['shortName'],
                    'alias' => $parsed['shortName'],
                    'namespaceParts' => $parsed['namespaceParts'],
                ];
            }

            return;
        }

        $options = $constraint[$constraintName];

        if (is_array($options) && isset($options['constraints'])) {
            foreach ($options['constraints'] as $nestedConstraint) {
                $this->extractFqcnFromConstraint($nestedConstraint, $fqcnConstraints);
            }
        }
    }

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     *
     * @return array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    protected function resolveFqcnConstraintAliases(array $fqcnConstraints): array
    {
        $shortNameMap = $this->groupConstraintsByShortName($fqcnConstraints);

        foreach ($shortNameMap as $shortName => $fqcns) {
            if (count($fqcns) === 1) {
                continue;
            }

            $vendorGroups = $this->groupByVendor($fqcns, $fqcnConstraints);
            $this->resolveVendorGroupAliases($vendorGroups, $shortName, $fqcnConstraints);
        }

        return $this->formatResolvedConstraints($fqcnConstraints);
    }

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     *
     * @return array<string, array<string>>
     */
    protected function groupConstraintsByShortName(array $fqcnConstraints): array
    {
        $shortNameMap = [];

        foreach ($fqcnConstraints as $normalized => $data) {
            $shortName = $data['shortName'];

            if (!isset($shortNameMap[$shortName])) {
                $shortNameMap[$shortName] = [];
            }

            $shortNameMap[$shortName][] = $normalized;
        }

        return $shortNameMap;
    }

    /**
     * @param array<string> $fqcns
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     *
     * @return array<string, array<string>>
     */
    protected function groupByVendor(array $fqcns, array $fqcnConstraints): array
    {
        $vendorGroups = [];

        foreach ($fqcns as $fqcn) {
            $vendor = $fqcnConstraints[$fqcn]['namespaceParts'][0] ?? '';

            if (!isset($vendorGroups[$vendor])) {
                $vendorGroups[$vendor] = [];
            }

            $vendorGroups[$vendor][] = $fqcn;
        }

        return $vendorGroups;
    }

    /**
     * @param array<string, array<string>> $vendorGroups
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     */
    protected function resolveVendorGroupAliases(array $vendorGroups, string $shortName, array &$fqcnConstraints): void
    {
        foreach ($vendorGroups as $vendor => $vendorFqcns) {
            if ($vendor === 'Symfony') {
                continue;
            }

            $this->resolveVendorAliases($vendorFqcns, $vendor, $shortName, $fqcnConstraints);
        }
    }

    /**
     * @param array<string> $vendorFqcns
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     */
    protected function resolveVendorAliases(array $vendorFqcns, string $vendor, string $shortName, array &$fqcnConstraints): void
    {
        if (count($vendorFqcns) === 1) {
            if (isset($fqcnConstraints[$vendorFqcns[0]])) {
                $fqcnConstraints[$vendorFqcns[0]]['alias'] = sprintf('%s%s', $vendor, $shortName);
            }

            return;
        }

        $this->resolveVendorCollisionAliases($vendorFqcns, $vendor, $shortName, $fqcnConstraints);
    }

    /**
     * @param array<string> $vendorFqcns
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     */
    protected function resolveVendorCollisionAliases(array $vendorFqcns, string $vendor, string $shortName, array &$fqcnConstraints): void
    {
        foreach ($vendorFqcns as $fqcn) {
            if (!isset($fqcnConstraints[$fqcn]['namespaceParts'])) {
                continue;
            }

            $namespaceParts = $fqcnConstraints[$fqcn]['namespaceParts'];
            $disambiguatingPart = $this->extractDisambiguatingPart($namespaceParts);
            $fqcnConstraints[$fqcn]['alias'] = sprintf('%s%s%s', $vendor, $disambiguatingPart, $shortName);
        }
    }

    /**
     * @param array<string> $namespaceParts
     */
    protected function extractDisambiguatingPart(array $namespaceParts): string
    {
        if (count($namespaceParts) >= 3) {
            return $namespaceParts[2];
        }

        if (count($namespaceParts) >= 2) {
            return $namespaceParts[1];
        }

        return '';
    }

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     *
     * @return array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    protected function formatResolvedConstraints(array $fqcnConstraints): array
    {
        $result = [];

        foreach ($fqcnConstraints as $data) {
            $result[$data['fqcn']] = [
                'fqcn' => $data['fqcn'],
                'shortName' => $data['shortName'],
                'alias' => $data['alias'],
            ];
        }

        return $result;
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
