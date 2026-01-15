<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validation\Merger;

class ValidationSchemaMerger implements ValidationSchemaMergerInterface
{
    /**
     * @param array<array<string, mixed>> $schemas
     *
     * @return array<string, mixed>
     */
    public function merge(array $schemas): array
    {
        if ($schemas === []) {
            return [];
        }

        if (count($schemas) === 1) {
            return reset($schemas);
        }

        $result = [];

        foreach ($schemas as $schema) {
            foreach ($schema as $httpMethod => $fieldConstraints) {
                if (!isset($result[$httpMethod])) {
                    $result[$httpMethod] = $fieldConstraints;

                    continue;
                }

                foreach ($fieldConstraints as $fieldName => $constraints) {
                    if (!isset($result[$httpMethod][$fieldName])) {
                        $result[$httpMethod][$fieldName] = $constraints;

                        continue;
                    }

                    $result[$httpMethod][$fieldName] = $this->deduplicateConstraints(
                        $result[$httpMethod][$fieldName],
                        $constraints,
                    );
                }
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $existingConstraints
     * @param array<mixed> $newConstraints
     *
     * @return array<mixed>
     */
    protected function deduplicateConstraints(array $existingConstraints, array $newConstraints): array
    {
        $mergedConstraints = array_merge($existingConstraints, $newConstraints);
        $constraintsByType = [];

        foreach ($mergedConstraints as $constraint) {
            $normalized = $this->normalizeConstraint($constraint);
            $type = $normalized['type'];

            // Last occurrence wins (override strategy for same constraint type)
            $constraintsByType[$type] = $constraint;
        }

        return array_values($constraintsByType);
    }

    /**
     * @param mixed $constraint
     *
     * @return array{type: string, parameters: array<mixed>}
     */
    protected function normalizeConstraint(mixed $constraint): array
    {
        if (is_string($constraint)) {
            return ['type' => $constraint, 'parameters' => []];
        }

        if (is_array($constraint) && isset($constraint[0]) && is_string($constraint[0])) {
            return ['type' => $constraint[0], 'parameters' => $constraint[1] ?? []];
        }

        if (is_array($constraint) && count($constraint) === 1) {
            $type = array_key_first($constraint);
            $parameters = is_array($constraint[$type]) ? $constraint[$type] : [];

            return ['type' => (string)$type, 'parameters' => $parameters];
        }

        return ['type' => 'Unknown', 'parameters' => $constraint];
    }
}
