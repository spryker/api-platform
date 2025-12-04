<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Parser;

use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;

class SchemaParser implements SchemaParserInterface
{
    /**
     * @param array<string, mixed> $rawSchema
     * @param array<string, mixed> $validationSchemas
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     *
     * @return array<string, mixed>
     */
    public function parse(array $rawSchema, SplFileInfo $file, array $validationSchemas = []): array
    {
        $filePath = $file->getRealPath() ?: $file->getPathname();

        if (!isset($rawSchema['resource'])) {
            throw new ApiSchemaValidationException(
                'Schema must have a "resource" key',
                $filePath,
            );
        }

        $resource = $rawSchema['resource'];

        if (!is_array($resource)) {
            throw new ApiSchemaValidationException(
                'Resource must be an array',
                $filePath,
            );
        }

        $parsedSchema = [
            'name' => $this->getValue($resource, 'name', null),
            'shortName' => $this->getValue($resource, 'shortName', $this->getValue($resource, 'name', null)),
            'description' => $this->getValue($resource, 'description', ''),
            'operations' => $this->normalizeOperations($resource, $filePath),
            'properties' => $this->normalizeProperties($resource, $filePath),
            'provider' => $this->getValue($resource, 'provider', null),
            'processor' => $this->getValue($resource, 'processor', null),
            'paginationItemsPerPage' => $this->getValue($resource, 'paginationItemsPerPage', null),
            'openapiContext' => $this->getValue($resource, 'openapiContext', []),
            'sourceFile' => $filePath,
            'sourceLayer' => $this->detectSourceLayer($filePath),
        ];

        $resourceName = $this->generateResourceKey($filePath);

        if (isset($validationSchemas[$resourceName])) {
            $parsedSchema['validation'] = $validationSchemas[$resourceName]['schema'];
            $parsedSchema['validationSourceFiles'] = [$validationSchemas[$resourceName]['sourceFile']];
        }

        return $parsedSchema;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function getValue(array $data, string $key, mixed $default): mixed
    {
        return $data[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     *
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeOperations(array $resource, string $filePath): array
    {
        $operations = $this->getValue($resource, 'operations', []);

        if (!is_array($operations)) {
            throw new ApiSchemaValidationException(
                'Operations must be an array',
                $filePath,
            );
        }

        $normalized = [];

        foreach ($operations as $key => $operation) {
            if (!is_array($operation)) {
                continue;
            }

            $operationType = is_string($key) ? $key : ($operation['type'] ?? null);

            if ($operationType === null) {
                continue;
            }

            // Operations are indexed by type to prevent duplicates and simplify lookup
            $normalized[$operationType] = $operation;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     *
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeProperties(array $resource, string $filePath): array
    {
        $properties = $this->getValue($resource, 'properties', []);

        if (!is_array($properties)) {
            throw new ApiSchemaValidationException(
                'Properties must be an array',
                $filePath,
            );
        }

        $normalized = [];

        foreach ($properties as $key => $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyName = is_string($key) ? $key : ($property['name'] ?? null);

            if ($propertyName === null) {
                continue;
            }

            $normalized[$propertyName] = [
                'name' => $propertyName,
                'type' => $this->normalizePropertyType($this->getValue($property, 'type', 'string')),
                'description' => $this->getValue($property, 'description', ''),
                'writable' => $this->getValue($property, 'writable', true),
                'readable' => $this->getValue($property, 'readable', true),
                'identifier' => $this->getValue($property, 'identifier', false),
                'required' => $this->getValue($property, 'required', false),
                'default' => $this->getValue($property, 'default', null),
                'openapiContext' => $this->getValue($property, 'openapiContext', []),
            ];
        }

        return $normalized;
    }

    protected function normalizePropertyType(mixed $type): string
    {
        if (!is_string($type)) {
            return 'string';
        }

        $normalized = strtolower(trim($type));

        // Map common type aliases to standard types
        return match ($normalized) {
            'int' => 'integer',
            'bool' => 'boolean',
            'str' => 'string',
            'arr' => 'array',
            default => $normalized,
        };
    }

    protected function detectSourceLayer(string $filePath): string
    {
        if (str_contains($filePath, '/Pyz/')) {
            return 'project';
        }

        if (str_contains($filePath, '/SprykerFeature/')) {
            return 'feature';
        }

        return 'core';
    }

    protected function generateResourceKey(string $filePath): string
    {
        $apiType = $this->extractApiTypeFromPath($filePath);
        $layer = $this->detectSourceLayer($filePath);
        $fileName = basename($filePath, '.resource.yml');
        $fileName = basename($fileName, '.resource.yaml');

        return sprintf('%s_%s_%s', $apiType, $layer, $fileName);
    }

    protected function extractApiTypeFromPath(string $filePath): string
    {
        if (preg_match('#/resources/api/([^/]+)/#', $filePath, $matches)) {
            return $matches[1];
        }

        return 'backoffice';
    }
}
