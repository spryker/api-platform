<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * Generates #[ApiProperty(...)] attributes for individual resource properties.
 *
 * Transforms property-level schema configuration into ApiProperty PHP attribute syntax,
 * including descriptions, read/write flags, identifiers, required flags, and OpenAPI context.
 *
 * Input property schema:
 * ```php
 * [
 *     'type' => 'string',
 *     'description' => "The customer's email address",
 *     'required' => true,
 *     'writable' => true,
 *     'readable' => true,
 *     'openapiContext' => [
 *         'example' => 'thomas.bell@spryker.com',
 *     ],
 * ]
 * ```
 *
 * Generated output:
 * ```php
 * #[ApiProperty(
 *     description: 'The customer\'s email address',
 *     required: true,
 *     openapiContext: ['example' => 'thomas.bell@spryker.com']
 * )]
 * ```
 *
 * For identifier properties:
 * ```php
 * #[ApiProperty(description: 'Unique customer reference', writable: false, identifier: true)]
 * ```
 *
 * Handles array and nested OpenAPI context formatting for complex examples and enum definitions.
 */
class PropertyAttributeGenerator
{
    /**
     * @param array<string, mixed> $property
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     */
    public function generate(
        array $property,
        array $validationSchema,
        array $operations,
        string $propertyName,
        string $resourceName
    ): string {
        $attributes = [];

        $apiPropertyParts = [];

        if (isset($property['description']) && $property['description'] !== '') {
            $apiPropertyParts[] = sprintf("description: '%s'", addslashes($property['description']));
        }

        if (isset($property['writable']) && $property['writable'] === false) {
            $apiPropertyParts[] = 'writable: false';
        }

        if (isset($property['readable']) && $property['readable'] === false) {
            $apiPropertyParts[] = 'readable: false';
        }

        if (isset($property['identifier']) && $property['identifier'] === true) {
            $apiPropertyParts[] = 'identifier: true';
        }

        if (isset($property['required']) && $property['required'] === true) {
            $apiPropertyParts[] = 'required: true';
        }

        if (isset($property['openapiContext']) && $property['openapiContext'] !== []) {
            $formattedContext = $this->formatOpenapiContext($property['openapiContext']);
            $apiPropertyParts[] = sprintf('openapiContext: %s', $formattedContext);
        }

        if (isset($property['uriTemplate']) && $property['uriTemplate'] !== '') {
            $apiPropertyParts[] = sprintf("uriTemplate: '%s'", addslashes($property['uriTemplate']));
        }

        if ($apiPropertyParts !== []) {
            $attributes[] = $this->formatApiPropertyAttribute($apiPropertyParts);
        }

        return implode("\n    ", $attributes);
    }

    /**
     * @param array<string> $apiPropertyParts
     */
    protected function formatApiPropertyAttribute(array $apiPropertyParts): string
    {
        if (count($apiPropertyParts) < 3) {
            return '#[ApiProperty(' . implode(', ', $apiPropertyParts) . ')]';
        }

        $indent2 = $this->indent(2);
        $indent1 = $this->indent(1);
        $content = $indent2 . implode(",\n" . $indent2, $apiPropertyParts);

        return sprintf("#[ApiProperty(\n%s,\n%s)]", $content, $indent1);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function formatOpenapiContext(array $context): string
    {
        $parts = [];

        foreach ($context as $key => $value) {
            $formattedValue = $this->formatOpenapiContextValue($value);
            $parts[] = sprintf("'%s' => %s", $key, $formattedValue);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    protected function formatOpenapiContextValue(mixed $value): string
    {
        if (is_array($value)) {
            return $this->formatOpenapiContextArray($value);
        }

        if (is_string($value)) {
            return sprintf("'%s'", addslashes($value));
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string)$value;
    }

    /**
     * @param array<mixed> $array
     */
    protected function formatOpenapiContextArray(array $array): string
    {
        if ($array === []) {
            return '[]';
        }

        $isAssociative = array_keys($array) !== range(0, count($array) - 1);

        if ($isAssociative) {
            $parts = [];

            foreach ($array as $key => $value) {
                $formattedValue = $this->formatOpenapiContextValue($value);
                $parts[] = sprintf("'%s' => %s", $key, $formattedValue);
            }

            return '[' . implode(', ', $parts) . ']';
        }

        $items = array_map(
            fn (mixed $item): string => $this->formatOpenapiContextValue($item),
            $array,
        );

        return '[' . implode(', ', $items) . ']';
    }

    protected function indent(int $level): string
    {
        return str_repeat('    ', $level);
    }
}
