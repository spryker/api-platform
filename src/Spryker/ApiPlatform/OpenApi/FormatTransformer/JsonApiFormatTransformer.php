<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\FormatTransformer;

use ArrayObject;

/**
 * Transformer for JSON:API format that handles:
 * 1. Adding example values to type and id fields
 * 2. Creating -post schema variants without id field for POST operations
 * 3. Fixing schema references based on HTTP method (only for base resource schemas)
 */
class JsonApiFormatTransformer implements FormatTransformerInterface
{
    protected const string FORMAT_SUFFIX = 'jsonapi';

    protected const string POST_SCHEMA_SUFFIX = '-post';

    protected const array MIME_TYPES = [
        'application/vnd.api+json',
    ];

    public function getFormatSuffix(): string
    {
        return static::FORMAT_SUFFIX;
    }

    public function getMimeTypes(): array
    {
        return static::MIME_TYPES;
    }

    /**
     * Transforms JSON:API schemas by:
     * 1. Adding example values to type and id fields
     * 2. Creating -post variants without id field for POST operations
     *
     * @param \ArrayObject<string, array<string, mixed>> $schemas
     *
     * @return \ArrayObject<string, array<string, mixed>>
     */
    public function transformSchemas(ArrayObject $schemas): ArrayObject
    {
        /** @var \ArrayObject<string, array<string, mixed>> $newSchemas */
        $newSchemas = new ArrayObject();

        foreach ($schemas as $schemaName => $schemaDefinition) {
            // Case when the resource is configured to not have an ID such as a /token request
            // Need to be caught here to prevent UI rendering exceptions.
            $schemaName = str_replace('_noid', '', $schemaName);

            if (!$this->isJsonApiSchema($schemaName)) {
                continue;
            }

            $resourceShortName = $this->extractResourceShortName($schemaName);
            $transformedSchema = $this->addExampleValues($schemaDefinition, $resourceShortName);
            $schemas[$schemaName] = $transformedSchema;

            $postSchemaName = $schemaName . static::POST_SCHEMA_SUFFIX;
            $postSchema = $this->createPostSchema($transformedSchema);
            $newSchemas[$postSchemaName] = $postSchema;
        }

        foreach ($newSchemas as $schemaName => $schemaDefinition) {
            $schemas[$schemaName] = $schemaDefinition;
        }

        return $schemas;
    }

    public function fixRequestBodyReference(string $ref, string $method): string
    {
        if (!str_contains($ref, sprintf('.%s', static::FORMAT_SUFFIX))) {
            if (!$this->isBaseResourceReference($ref)) {
                return $ref;
            }

            $ref = $this->appendFormatSuffix($ref);
        }

        if ($method === 'post') {
            $ref = $this->appendPostSuffix($ref);
        }

        return $ref;
    }

    /**
     * Checks if a schema reference is for a base resource (not operation-specific).
     *
     * Base resource schemas don't have dots in the name, or only have dots for format suffixes.
     * Operation-specific schemas like "customers.jsonMergePatch" should not be transformed.
     */
    protected function isBaseResourceReference(string $ref): bool
    {
        $parts = explode('/', $ref);
        $schemaName = end($parts);

        return !str_contains($schemaName, '.') ||
               str_contains($schemaName, sprintf('.%s', static::FORMAT_SUFFIX));
    }

    /**
     * Checks if schema is a JSON:API schema.
     */
    protected function isJsonApiSchema(string $schemaName): bool
    {
        return str_ends_with($schemaName, sprintf('.%s', static::FORMAT_SUFFIX));
    }

    /**
     * Extracts resource short name from schema name.
     *
     * Example: "customers.jsonapi" -> "customers"
     */
    protected function extractResourceShortName(string $schemaName): string
    {
        return str_replace(sprintf('.%s', static::FORMAT_SUFFIX), '', $schemaName);
    }

    /**
     * Adds example values to type and id fields in JSON:API schema.
     *
     * @param \ArrayObject<string, mixed>|array<string, mixed> $schemaDefinition
     *
     * @return array<string, mixed>
     */
    protected function addExampleValues(array|ArrayObject $schemaDefinition, string $resourceShortName): array
    {
        $schemaDefinition = $schemaDefinition instanceof ArrayObject ? $schemaDefinition->getArrayCopy() : $schemaDefinition;

        if (!isset($schemaDefinition['properties']['data']['properties'])) {
            return $schemaDefinition;
        }

        if (isset($schemaDefinition['properties']['data']['properties']['type']) && is_array($schemaDefinition['properties']['data']['properties']['type'])) {
            $schemaDefinition['properties']['data']['properties']['type']['example'] = $resourceShortName;
        }

        if (isset($schemaDefinition['properties']['data']['properties']['id']) && is_array($schemaDefinition['properties']['data']['properties']['id'])) {
            $schemaDefinition['properties']['data']['properties']['id']['example'] = sprintf('/%s/1', $resourceShortName);
        }

        return $schemaDefinition;
    }

    /**
     * Creates POST schema variant by removing id field entirely for POST operations.
     *
     * @param array<string, mixed> $schemaDefinition
     *
     * @return array<string, mixed>
     */
    protected function createPostSchema(array $schemaDefinition): array
    {
        $postSchema = $schemaDefinition;

        if (!isset($postSchema['properties']['data'])) {
            return $postSchema;
        }

        if (isset($postSchema['properties']['data']['required'])) {
            $postSchema['properties']['data']['required'] = array_values(array_filter(
                $postSchema['properties']['data']['required'],
                fn ($field) => $field !== 'id',
            ));
        }

        if (isset($postSchema['properties']['data']['properties']['id'])) {
            unset($postSchema['properties']['data']['properties']['id']);
        }

        return $postSchema;
    }

    /**
     * Appends format suffix to schema reference.
     *
     * Example: "#/components/schemas/customers" -> "#/components/schemas/customers.jsonapi"
     */
    protected function appendFormatSuffix(string $ref): string
    {
        if (str_contains($ref, sprintf('.%s', static::FORMAT_SUFFIX))) {
            return $ref;
        }

        $parts = explode('/', $ref);
        $schemaName = array_pop($parts);

        $schemaName .= sprintf('.%s', static::FORMAT_SUFFIX);
        $parts[] = $schemaName;

        return implode('/', $parts);
    }

    /**
     * Appends -post suffix to schema reference for POST operations.
     *
     * Example: "#/components/schemas/customers.jsonapi" -> "#/components/schemas/customers.jsonapi-post"
     */
    protected function appendPostSuffix(string $ref): string
    {
        if (str_contains($ref, static::POST_SCHEMA_SUFFIX)) {
            return $ref;
        }

        return $ref . static::POST_SCHEMA_SUFFIX;
    }
}
