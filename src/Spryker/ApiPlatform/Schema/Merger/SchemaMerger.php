<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Merger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spryker\ApiPlatform\Schema\Validation\Merger\ValidationSchemaMergerInterface;

class SchemaMerger implements SchemaMergerInterface
{
    protected readonly LoggerInterface $logger;

    public function __construct(
        protected readonly ValidationSchemaMergerInterface $validationSchemaMerger,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param array<array<string, mixed>> $schemas
     *
     * @return array<string, mixed>
     */
    public function merge(array $schemas, string $resourceName, string $apiType): array
    {
        if ($schemas === []) {
            return [];
        }

        if (count($schemas) === 1) {
            $schema = reset($schemas);

            return $this->enrichWithMetadata($schema, [$this->createSourceInfo($schema)]);
        }

        $grouped = $this->groupByLayer($schemas);
        $contributingSources = [];

        // Start with core as base following Spryker's override hierarchy
        // This ensures core provides the foundation that higher layers extend
        $result = [];

        if (isset($grouped['core'])) {
            $result = $this->deepCopy($grouped['core']);
            $contributingSources[] = $this->createSourceInfo($grouped['core']);

            $this->logger->info('Using core schema as base', [
                'resource' => $resourceName,
                'file' => $grouped['core']['sourceFile'] ?? 'unknown',
            ]);
        }

        // Merge feature layer (properties override core)
        // Feature layer extends core with additional functionality
        if (isset($grouped['feature'])) {
            $result = $this->deepMerge($result, $grouped['feature']);
            $contributingSources[] = $this->createSourceInfo($grouped['feature']);

            $this->logger->info('Merged feature schema', [
                'resource' => $resourceName,
                'file' => $grouped['feature']['sourceFile'] ?? 'unknown',
            ]);
        }

        // Merge project layer (properties override feature/core)
        // Project layer has highest priority for customization
        if (isset($grouped['project'])) {
            $result = $this->deepMerge($result, $grouped['project']);
            $contributingSources[] = $this->createSourceInfo($grouped['project']);

            $this->logger->info('Merged project schema', [
                'resource' => $resourceName,
                'file' => $grouped['project']['sourceFile'] ?? 'unknown',
            ]);
        }

        // Handle edge case: only feature exists (no core)
        if ($result === [] && isset($grouped['feature'])) {
            $result = $grouped['feature'];
            $contributingSources[] = $this->createSourceInfo($grouped['feature']);

            $this->logger->warning('No core schema found, using feature as base', [
                'resource' => $resourceName,
            ]);
        }

        $result = $this->mergeValidationSchemas($result, $schemas);

        return $this->enrichWithMetadata($result, $contributingSources);
    }

    /**
     * @param array<array<string, mixed>> $schemas
     *
     * @return array<string, array<string, mixed>>
     */
    protected function groupByLayer(array $schemas): array
    {
        $grouped = [];

        foreach ($schemas as $schema) {
            $layer = $schema['sourceLayer'] ?? 'core';

            if (isset($grouped[$layer])) {
                $this->logger->info('Multiple schemas found for same layer, merging them', [
                    'layer' => $layer,
                    'previous' => $grouped[$layer]['sourceFile'] ?? 'unknown',
                    'current' => $schema['sourceFile'] ?? 'unknown',
                ]);

                $grouped[$layer] = $this->deepMerge($grouped[$layer], $schema);

                continue;
            }

            $grouped[$layer] = $schema;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    protected function deepMerge(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if ($key === 'sourceFile' || $key === 'sourceLayer') {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (!isset($result[$key])) {
                $result[$key] = $value;

                continue;
            }

            // Properties merge deeply - individual properties from higher layers override lower layers
            // This provides flexibility while maintaining consistency
            if ($key === 'properties' && is_array($value) && is_array($result[$key])) {
                $result[$key] = $this->mergeProperties($result[$key], $value);

                continue;
            }

            // Operations are replaced per operation type
            if ($key === 'operations' && is_array($value) && is_array($result[$key])) {
                $result[$key] = array_merge($result[$key], $value);

                continue;
            }

            if (is_array($value) && is_array($result[$key])) {
                $result[$key] = array_merge($result[$key], $value);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $baseProperties
     * @param array<string, mixed> $overrideProperties
     *
     * @return array<string, mixed>
     */
    protected function mergeProperties(array $baseProperties, array $overrideProperties): array
    {
        $result = $baseProperties;

        foreach ($overrideProperties as $propertyName => $overrideProperty) {
            if (!isset($result[$propertyName])) {
                $result[$propertyName] = $overrideProperty;

                continue;
            }

            if (!is_array($overrideProperty)) {
                continue;
            }

            $result[$propertyName] = array_merge($result[$propertyName], $overrideProperty);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    protected function deepCopy(array $schema): array
    {
        return unserialize(serialize($schema), ['allowed_classes' => false]);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, string>
     */
    protected function createSourceInfo(array $schema): array
    {
        return [
            'layer' => $schema['sourceLayer'] ?? 'unknown',
            'file' => $schema['sourceFile'] ?? 'unknown',
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<array<string, string>> $sources
     *
     * @return array<string, mixed>
     */
    protected function enrichWithMetadata(array $schema, array $sources): array
    {
        $schema['_metadata'] = [
            'contributingSources' => $sources,
            'mergedAt' => date('c'),
        ];

        return $schema;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<array<string, mixed>> $schemas
     *
     * @return array<string, mixed>
     */
    protected function mergeValidationSchemas(array $result, array $schemas): array
    {
        $validationSchemas = [];
        $validationSourceFiles = [];

        foreach ($schemas as $schema) {
            if (isset($schema['validation'])) {
                $validationSchemas[] = $schema['validation'];
            }

            if (isset($schema['validationSourceFiles'])) {
                $validationSourceFiles = array_merge($validationSourceFiles, $schema['validationSourceFiles']);
            }
        }

        if ($validationSchemas !== []) {
            $result['validation'] = $this->validationSchemaMerger->merge($validationSchemas);
            $result['validationSourceFiles'] = array_unique($validationSourceFiles);
        }

        return $result;
    }
}
