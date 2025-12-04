<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator;

use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Schema\Validator\Rules\MergeValidationRule;

class SchemaValidator implements SchemaValidatorInterface
{
    /**
     * @param iterable<\Spryker\ApiPlatform\Schema\Validator\Rules\ValidationRuleInterface> $postMergeRules
     */
    public function __construct(
        protected readonly iterable $postMergeRules,
        protected readonly MergeValidationRule $mergeValidationRule,
    ) {
    }

    /**
     * @param array<string, mixed> $mergedSchema
     * @param array<string, mixed>|null $coreSchema
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     */
    public function validatePostMerge(array $mergedSchema, ?array $coreSchema = null): void
    {
        if (!$this->isStructurallyValid($mergedSchema)) {
            throw new ApiSchemaValidationException(
                'Merged schema is missing required top-level keys',
                $mergedSchema['sourceFile'] ?? 'unknown file',
            );
        }

        $errors = $this->collectErrors($this->postMergeRules, $mergedSchema);

        if ($coreSchema !== null && $mergedSchema['sourceLayer'] === 'project') {
            $errors = array_merge(
                $errors,
                $this->mergeValidationRule->validate([
                    'coreSchema' => $coreSchema,
                    'projectSchema' => $mergedSchema,
                ]),
            );
        }

        if ($errors !== []) {
            throw new ApiSchemaValidationException(
                $this->formatErrors($errors),
                $mergedSchema['sourceFile'] ?? 'unknown file',
            );
        }
    }

    /**
     * @param array<string, mixed> $schema
     */
    protected function isStructurallyValid(array $schema): bool
    {
        return !empty($schema['shortName']) && isset($schema['properties']);
    }

    /**
     * @param iterable<\Spryker\ApiPlatform\Schema\Validator\Rules\ValidationRuleInterface> $rules
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function collectErrors(iterable $rules, array $schema): array
    {
        $errors = [];

        foreach ($rules as $rule) {
            $errors = array_merge($errors, $rule->validate($schema));
        }

        return $errors;
    }

    /**
     * @param array<string> $errors
     */
    protected function formatErrors(array $errors): string
    {
        if ($errors === []) {
            return 'Unknown validation error';
        }

        $count = count($errors);
        $header = sprintf('Schema validation failed with %d error%s:', $count, $count > 1 ? 's' : '');

        return $header . "\n  - " . implode("\n  - ", $errors);
    }
}
