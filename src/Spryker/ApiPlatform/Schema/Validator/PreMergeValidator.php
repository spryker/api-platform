<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator;

use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;

class PreMergeValidator implements PreMergeValidatorInterface
{
    protected const array VALID_TYPES = [
        'string',
        'integer',
        'number',
        'boolean',
        'array',
        'object',
        'mixed',
    ];

    /**
     * @param array<string, mixed> $schema
     */
    public function validate(array $schema, string $filePath): void
    {
        $this->validateResourceName($schema, $filePath);
        $this->validateProperties($schema, $filePath);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     */
    protected function validateResourceName(array $schema, string $filePath): void
    {
        if (!isset($schema['name']) || $schema['name'] === '') {
            throw new ApiSchemaValidationException(
                'Resource must have a "name" property',
                $filePath,
            );
        }
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     */
    protected function validateProperties(array $schema, string $filePath): void
    {
        if (!isset($schema['properties']) || !is_array($schema['properties'])) {
            return;
        }

        $errors = [];

        foreach ($schema['properties'] as $propertyName => $property) {
            if (!is_array($property)) {
                continue;
            }

            if (!isset($property['type'])) {
                continue;
            }

            $type = $property['type'];

            if (!in_array($type, static::VALID_TYPES, true)) {
                $codeBucketContext = '';

                /** @phpstan-ignore notIdentical.alwaysTrue */
                if (isset($schema['codeBucket']) && $schema['codeBucket'] !== null) {
                    $codeBucketContext = sprintf(' (CodeBucket: %s)', $schema['codeBucket']);
                }

                $suggestion = $this->suggestType($type);

                $errors[] = sprintf(
                    'Invalid property type "%s" for property "%s"%s',
                    $type,
                    $propertyName,
                    $codeBucketContext,
                );

                $errors[] = sprintf('  Valid types: %s', implode(', ', static::VALID_TYPES));

                if ($suggestion !== null) {
                    $errors[] = sprintf('  Hint: %s', $suggestion);
                }
            }
        }

        if ($errors !== []) {
            $errorMessage = sprintf(
                "Pre-merge validation failed:\n\n%s",
                implode("\n", $errors),
            );

            throw new ApiSchemaValidationException($errorMessage, $filePath);
        }
    }

    protected function suggestType(string $invalidType): ?string
    {
        $suggestions = [
            'int' => "Use 'integer' for whole numbers",
            'float' => "Use 'number' for decimal numbers (or 'float' which normalizes to 'number')",
            'double' => "Use 'number' for decimal numbers",
            'bool' => "Use 'boolean' for true/false values",
            'str' => "Use 'string' for text",
            'arr' => "Use 'array' for lists",
        ];

        return $suggestions[$invalidType] ?? null;
    }
}
