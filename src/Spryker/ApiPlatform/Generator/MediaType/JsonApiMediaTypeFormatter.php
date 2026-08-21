<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\MediaType;

/**
 * Formatter for JSON:API media type (application/vnd.api+json).
 *
 * Generates OpenAPI request body examples following the JSON:API specification format.
 * JSON:API requires a specific structure with a top-level 'data' object containing
 * 'type' and 'attributes' fields.
 *
 * Example output structure:
 * ```php
 * [
 *     'data' => [
 *         'type' => 'customers',
 *         'attributes' => [
 *             'email' => 'customer@example.com',
 *             'firstName' => 'John',
 *             'lastName' => 'Doe',
 *         ],
 *     ],
 * ]
 * ```
 *
 * The formatter automatically:
 * - Filters out identifier properties (not writable in JSON:API)
 * - Filters out read-only properties for write operations (Post, Patch, Put)
 * - Uses example values from property openapiContext configuration
 * - Structures data according to JSON:API specification
 */
class JsonApiMediaTypeFormatter implements MediaTypeFormatterInterface
{
    public function getMediaType(): string
    {
        return 'application/vnd.api+json';
    }

    /**
     * @param array<string, mixed> $parsedSchema
     *
     * @return array<string, mixed>
     */
    public function buildExample(array $parsedSchema, string $operationType): array
    {
        $example = [
            'data' => [
                'type' => $parsedSchema['shortName'] ?? 'resource',
                'attributes' => [],
            ],
        ];

        $properties = $parsedSchema['properties'] ?? [];

        foreach ($properties as $propertyName => $propertyConfig) {
            if ($propertyConfig['identifier'] ?? false) {
                continue;
            }

            if (in_array($operationType, ['Post', 'Patch', 'Put'], true)) {
                if (isset($propertyConfig['writable']) && $propertyConfig['writable'] === false) {
                    continue;
                }
            }

            if (isset($propertyConfig['openapiContext']['example'])) {
                $example['data']['attributes'][$propertyName] = $propertyConfig['openapiContext']['example'];
            }
        }

        return $example;
    }

    /**
     * @param array<string, mixed> $example
     */
    public function formatExampleAsCode(array $example): string
    {
        return var_export($example, true);
    }
}
