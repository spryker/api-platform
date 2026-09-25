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
 * A property carrying `responseOptional: true` describes the response contract, not the request
 * schema (unrelated to `required`), and renders as an `extraProperties` entry:
 * ```php
 * #[ApiProperty(extraProperties: ['responseOptional' => true])]
 * ```
 *
 * A property carrying `syntheticIdentifier: true` is an identifier a singleton resource declares
 * only so API Platform can mint an IRI for it; the wire carries `data.id: null`, as the legacy Glue
 * REST API did. It renders the same way:
 * ```php
 * #[ApiProperty(identifier: true, extraProperties: ['syntheticIdentifier' => true])]
 * ```
 *
 * A property carrying `collectionOnly: true` describes the collection response alone - pagination
 * metadata - so the contract coverage gate demands it of collection operations only, and
 * `itemOnly: true` is its mirror for a resource whose list answers a summary:
 * ```php
 * #[ApiProperty(extraProperties: ['collectionOnly' => true])]
 * #[ApiProperty(extraProperties: ['itemOnly' => true])]
 * ```
 *
 * Handles array and nested OpenAPI context formatting for complex examples and enum definitions.
 */
class PropertyAttributeGenerator
{
    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeTruthCollector::EXTRA_PROPERTY_RESPONSE_OPTIONAL
     */
    protected const string EXTRA_PROPERTY_RESPONSE_OPTIONAL = 'responseOptional';

    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader::EXTRA_PROPERTY_SYNTHETIC_IDENTIFIER
     */
    protected const string EXTRA_PROPERTY_SYNTHETIC_IDENTIFIER = 'syntheticIdentifier';

    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeTruthCollector::EXTRA_PROPERTY_COLLECTION_ONLY
     */
    protected const string EXTRA_PROPERTY_COLLECTION_ONLY = 'collectionOnly';

    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\ResponseAttributeTruthCollector::EXTRA_PROPERTY_ITEM_ONLY
     */
    protected const string EXTRA_PROPERTY_ITEM_ONLY = 'itemOnly';

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

        if (isset($property[SchemaKey::DESCRIPTION]) && $property[SchemaKey::DESCRIPTION] !== '') {
            $apiPropertyParts[] = sprintf("description: '%s'", addslashes($property[SchemaKey::DESCRIPTION]));
        }

        if (isset($property[SchemaKey::WRITABLE]) && $property[SchemaKey::WRITABLE] === false) {
            $apiPropertyParts[] = 'writable: false';
        }

        if (isset($property[SchemaKey::READABLE]) && $property[SchemaKey::READABLE] === false) {
            $apiPropertyParts[] = 'readable: false';
        }

        if (isset($property[SchemaKey::IDENTIFIER]) && $property[SchemaKey::IDENTIFIER] === true) {
            $apiPropertyParts[] = 'identifier: true';
        }

        if (isset($property[SchemaKey::IDENTIFIER]) && $property[SchemaKey::IDENTIFIER] === false) {
            $apiPropertyParts[] = 'identifier: false';
        }

        if (isset($property[SchemaKey::REQUIRED]) && $property[SchemaKey::REQUIRED] === true) {
            $apiPropertyParts[] = 'required: true';
        }

        $extraProperties = [];

        if (isset($property[SchemaKey::RESPONSE_OPTIONAL]) && $property[SchemaKey::RESPONSE_OPTIONAL] === true) {
            $extraProperties[] = static::EXTRA_PROPERTY_RESPONSE_OPTIONAL;
        }

        if (isset($property[SchemaKey::SYNTHETIC_IDENTIFIER]) && $property[SchemaKey::SYNTHETIC_IDENTIFIER] === true) {
            $extraProperties[] = static::EXTRA_PROPERTY_SYNTHETIC_IDENTIFIER;
        }

        if (isset($property[SchemaKey::COLLECTION_ONLY]) && $property[SchemaKey::COLLECTION_ONLY] === true) {
            $extraProperties[] = static::EXTRA_PROPERTY_COLLECTION_ONLY;
        }

        if (isset($property[SchemaKey::ITEM_ONLY]) && $property[SchemaKey::ITEM_ONLY] === true) {
            $extraProperties[] = static::EXTRA_PROPERTY_ITEM_ONLY;
        }

        if ($extraProperties !== []) {
            $apiPropertyParts[] = sprintf(
                'extraProperties: [%s]',
                implode(', ', array_map(static fn (string $key): string => sprintf("'%s' => true", $key), $extraProperties)),
            );
        }

        $openapiContext = $property[SchemaKey::OPEN_API_CONTEXT] ?? [];

        if (isset($property[SchemaKey::TYPE]) && $property[SchemaKey::TYPE] === 'map') {
            $openapiContext = array_merge([SchemaKey::TYPE => 'object'], $openapiContext);
        }

        if ($openapiContext !== []) {
            $formattedContext = $this->formatOpenapiContext($openapiContext);
            $apiPropertyParts[] = sprintf('openapiContext: %s', $formattedContext);
        }

        if (isset($property[SchemaKey::URI_TEMPLATE]) && $property[SchemaKey::URI_TEMPLATE] !== '') {
            $apiPropertyParts[] = sprintf("uriTemplate: '%s'", addslashes($property[SchemaKey::URI_TEMPLATE]));
        }

        if ($apiPropertyParts !== []) {
            $attributes[] = $this->formatApiPropertyAttribute($apiPropertyParts);
        }

        $groupsAttribute = $this->formatGroupsAttribute($property);

        if ($groupsAttribute !== '') {
            $attributes[] = $groupsAttribute;
        }

        return implode("\n    ", $attributes);
    }

    /**
     * @param array<string, mixed> $property
     */
    protected function formatGroupsAttribute(array $property): string
    {
        $groups = $property['groups'] ?? null;

        if (!is_array($groups) || $groups === []) {
            return '';
        }

        $formattedGroups = array_map(
            static fn (mixed $group): string => sprintf("'%s'", addslashes((string)$group)),
            array_values($groups),
        );

        return sprintf('#[Groups([%s])]', implode(', ', $formattedGroups));
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
