<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validation\Collector;

use Spryker\ApiPlatform\Contract\Coverage\ConstraintRuleMapper;

/**
 * Reports the validation rules a resource loses because the file declaring them sits behind an
 * `excludedPathFragments` entry.
 *
 * The exclusion is path-shaped, not rule-shaped, so every rule the skipped file declares on top of
 * the surviving one disappears with it, unnoticed. This diffs the skipped file against the merged
 * schema and returns only what is genuinely lost.
 */
class ExcludedValidationRuleCollector
{
    protected const string CONSTRAINT_COLLECTION = 'Collection';

    protected const string CONSTRAINT_LENGTH = 'Length';

    protected const string PATH_SEPARATOR = '.';

    /**
     * The rules the excluded schema declares that the merged schema does not, as
     * `<httpMethod>.<dotted attribute path>.<rule>` strings.
     *
     * @param array<string, mixed> $excludedSchema
     * @param array<string, mixed> $mergedSchema
     *
     * @return array<string>
     */
    public function collectLostRules(array $excludedSchema, array $mergedSchema): array
    {
        $lostRules = array_values(array_diff(
            $this->collectRules($excludedSchema),
            $this->collectRules($mergedSchema),
        ));

        sort($lostRules);

        return $lostRules;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function collectRules(array $schema): array
    {
        $rules = [];

        foreach ($schema as $httpMethod => $fieldConstraints) {
            if (!is_array($fieldConstraints)) {
                continue;
            }

            foreach ($fieldConstraints as $fieldName => $constraints) {
                if (!is_array($constraints)) {
                    continue;
                }

                foreach ($this->walkConstraints($constraints, (string)$httpMethod . static::PATH_SEPARATOR . (string)$fieldName) as $rule) {
                    $rules[$rule] = true;
                }
            }
        }

        return array_keys($rules);
    }

    /**
     * @param array<mixed> $constraints
     *
     * @return array<string>
     */
    protected function walkConstraints(array $constraints, string $path): array
    {
        $rules = [];

        foreach ($constraints as $constraint) {
            foreach ($this->walkConstraint($constraint, $path) as $rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return array<string>
     */
    protected function walkConstraint(mixed $constraint, string $path): array
    {
        if (is_string($constraint)) {
            return [$path . static::PATH_SEPARATOR . $this->shortConstraintName($constraint)];
        }

        if (!is_array($constraint) || $constraint === []) {
            return [];
        }

        $constraintName = $this->shortConstraintName((string)array_key_first($constraint));
        $options = $constraint[array_key_first($constraint)];

        if (!is_array($options)) {
            return [$path . static::PATH_SEPARATOR . $constraintName];
        }

        // `Collection` is handled first because its rules hang off named fields and each one earns a
        // path segment; the remaining cascading constraints only wrap, so their children keep the path.
        if ($constraintName === static::CONSTRAINT_COLLECTION) {
            return $this->walkCollectionFields($options['fields'] ?? [], $path);
        }

        if (in_array($constraintName, ConstraintRuleMapper::CASCADING, true)) {
            return $this->walkConstraints($options['constraints'] ?? [], $path);
        }

        if ($constraintName === static::CONSTRAINT_LENGTH) {
            return $this->boundedLengthRules($options, $path);
        }

        return [$path . static::PATH_SEPARATOR . $constraintName];
    }

    /**
     * @return array<string>
     */
    protected function walkCollectionFields(mixed $fields, string $path): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $rules = [];

        foreach ($fields as $fieldName => $constraints) {
            if (!is_array($constraints)) {
                continue;
            }

            foreach ($this->walkConstraints($constraints, $path . static::PATH_SEPARATOR . (string)$fieldName) as $rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * A `Length` carries one rule per bound it sets, so a schema that tightens only `min` is not
     * mistaken for a repeat of one that sets only `max`.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string>
     */
    protected function boundedLengthRules(array $options, string $path): array
    {
        $rules = [];

        foreach (['min', 'max'] as $bound) {
            if (isset($options[$bound])) {
                $rules[] = sprintf('%s.%s.%s', $path, static::CONSTRAINT_LENGTH, $bound);
            }
        }

        return $rules;
    }

    protected function shortConstraintName(string $constraintName): string
    {
        $parts = explode('\\', ltrim($constraintName, '\\'));

        return (string)array_pop($parts);
    }
}
