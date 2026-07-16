<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Merger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
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
            $schema = $this->mergeValidationSchemas($schema, [$schema]);

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
     * @param array<array<string, mixed>> $codeBucketSchemas
     * @param array<string, mixed> $baseSchema
     *
     * @return array<string, mixed>
     */
    public function mergeWithCodeBucketInheritance(
        array $codeBucketSchemas,
        array $baseSchema,
        string $resourceName,
        string $apiType,
    ): array {
        $result = $this->deepCopy($baseSchema);

        $grouped = $this->groupByLayer($codeBucketSchemas);
        $contributingSources = $baseSchema['_metadata']['contributingSources'] ?? [];

        if (isset($grouped['core'])) {
            $result = $this->deepMerge($result, $grouped['core']);
            $contributingSources[] = $this->createSourceInfo($grouped['core']);

            $this->logger->info('Merged CodeBucket core schema', [
                'resource' => $resourceName,
                'file' => $grouped['core']['sourceFile'] ?? 'unknown',
            ]);
        }

        if (isset($grouped['feature'])) {
            $result = $this->deepMerge($result, $grouped['feature']);
            $contributingSources[] = $this->createSourceInfo($grouped['feature']);

            $this->logger->info('Merged CodeBucket feature schema', [
                'resource' => $resourceName,
                'file' => $grouped['feature']['sourceFile'] ?? 'unknown',
            ]);
        }

        if (isset($grouped['project'])) {
            $result = $this->deepMerge($result, $grouped['project']);
            $contributingSources[] = $this->createSourceInfo($grouped['project']);

            $this->logger->info('Merged CodeBucket project schema', [
                'resource' => $resourceName,
                'file' => $grouped['project']['sourceFile'] ?? 'unknown',
            ]);
        }

        $result = $this->mergeValidationSchemas($result, $codeBucketSchemas);

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

                $previousFiles = $grouped[$layer]['_layerSourceFiles'] ?? [$grouped[$layer]['sourceFile'] ?? null];
                $currentFile = $schema['sourceFile'] ?? null;

                $grouped[$layer] = $this->deepMerge($grouped[$layer], $schema);

                $allFiles = array_filter(array_merge($previousFiles, [$currentFile]));
                $grouped[$layer]['_layerSourceFiles'] = $allFiles;

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
                $result[$key] = $this->mergeProperties($result[$key], $value, $override);

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
     * @param array<string, mixed> $overrideSchema
     *
     * @return array<string, mixed>
     */
    protected function mergeProperties(array $baseProperties, array $overrideProperties, array $overrideSchema): array
    {
        $result = $baseProperties;

        foreach ($overrideProperties as $propertyName => $overrideProperty) {
            if (!isset($result[$propertyName])) {
                $result[$propertyName] = $overrideProperty;

                if (is_array($result[$propertyName])) {
                    $result[$propertyName]['_contributingFiles'] = [
                        [
                            'file' => $overrideSchema['sourceFile'] ?? 'unknown',
                            'layer' => $overrideSchema['sourceLayer'] ?? 'unknown',
                            'codeBucket' => $overrideSchema['codeBucket'] ?? null,
                        ],
                    ];
                }

                continue;
            }

            if (!is_array($overrideProperty)) {
                continue;
            }

            $existingFiles = $result[$propertyName]['_contributingFiles'] ?? [];
            $baseProperty = $result[$propertyName];

            $contributingFile = [
                'file' => $overrideSchema['sourceFile'] ?? 'unknown',
                'layer' => $overrideSchema['sourceLayer'] ?? 'unknown',
                'codeBucket' => $overrideSchema['codeBucket'] ?? null,
            ];

            // Escape hatch: `replace: true` means the author deliberately re-shapes the inherited
            // property. Take the override wholesale (base discarded), strip the meta key so it never
            // reaches generated output, and skip the shape-conflict guard below.
            if (($overrideProperty['replace'] ?? null) === true) {
                unset($overrideProperty['replace']);
                $result[$propertyName] = $overrideProperty;
                $result[$propertyName]['_contributingFiles'] = array_merge($existingFiles, [$contributingFile]);

                continue;
            }

            // Fail loud before the shallow merge silently resolves a shape conflict last-wins: one
            // contributor declaring a typed nested object and another the same property as a plain
            // map/scalar/array would otherwise drop the typed value object (or the plain field).
            $this->assertNoShapeConflict($propertyName, $baseProperty, $overrideProperty, $overrideSchema);

            $result[$propertyName] = array_merge($baseProperty, $overrideProperty);

            // A shallow merge would let a later contributor's nested `properties` replace an earlier
            // one's, so two modules each contributing fields to the same object property (e.g. cart
            // `calculations`, owned partly by Discount and ProductOptions) would lose fields. Union the
            // nested fields instead, recursively — covers both `type: object` and object-collection
            // (`items`) shapes.
            $result[$propertyName] = $this->mergeNestedObjectProperties($baseProperty, $overrideProperty, $result[$propertyName], $overrideSchema);

            $result[$propertyName]['_contributingFiles'] = array_merge($existingFiles, [$contributingFile]);
        }

        return $result;
    }

    /**
     * Fails loud when two contributors declare the same property with conflicting shapes — one a
     * typed nested object (`type: object` with `properties`) or an object collection (`type: array`
     * with `items.properties`), the other something structurally different (a map / scalar / plain
     * array / object-without-properties, or the other structured kind). A silent last-wins merge
     * here would drop the typed value object or the plain field, so generation stops and names both
     * offenders. Deliberate re-shapes opt out with `replace: true` on the overriding property.
     *
     * Same-shape overrides (object+object, collection+collection) and attribute-only overrides (no
     * `type` declared) fall through to the field union / shallow attribute merge and never throw.
     * Applies same-layer and cross-layer, since both routes reach this method.
     *
     * @param string $propertyName
     * @param array<string, mixed> $baseProperty
     * @param array<string, mixed> $overrideProperty
     * @param array<string, mixed> $overrideSchema
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     *
     * @return void
     */
    protected function assertNoShapeConflict(
        string $propertyName,
        array $baseProperty,
        array $overrideProperty,
        array $overrideSchema,
    ): void {
        $baseKind = $this->propertyShapeKind($baseProperty);
        $overrideKind = $this->propertyShapeKind($overrideProperty);

        // No conflict when either side omits `type` (an attribute-only override) or both declare the
        // same shape kind (object+object and collection+collection deep-merge; other+other shallow-merges).
        if ($baseKind === null || $overrideKind === null || $baseKind === $overrideKind) {
            return;
        }

        throw new ApiSchemaGenerationException(
            sprintf(
                'Conflicting shapes for property "%s": %s declares it as %s, but %s declares it as %s. '
                . 'A silent last-wins merge would drop the typed value object or the plain field. Reconcile '
                . 'the two declarations, or set `replace: true` on the overriding property to re-shape it deliberately.',
                $propertyName,
                $this->describeContributingFiles($baseProperty),
                $this->describeShapeKind($baseKind, $baseProperty),
                $overrideSchema['sourceFile'] ?? 'unknown',
                $this->describeShapeKind($overrideKind, $overrideProperty),
            ),
        );
    }

    /**
     * Classifies a property's declared shape: `object` (typed object with `properties`), `collection`
     * (object collection: `type: array` with `items.properties`), `other` (any other declared type,
     * e.g. map/scalar/plain array/object-without-properties), or null when no `type` is declared.
     *
     * @param array<string, mixed> $property
     *
     * @return string|null
     */
    protected function propertyShapeKind(array $property): ?string
    {
        if (!isset($property['type'])) {
            return null;
        }

        $type = $property['type'];

        if ($type === 'object' && isset($property['properties']) && is_array($property['properties'])) {
            return 'object';
        }

        if ($type === 'array' && isset($property['items']['properties']) && is_array($property['items']['properties'])) {
            return 'collection';
        }

        return 'other';
    }

    /**
     * @param string $kind
     * @param array<string, mixed> $property
     *
     * @return string
     */
    protected function describeShapeKind(string $kind, array $property): string
    {
        return match ($kind) {
            'object' => 'a typed object (`type: object` with `properties`)',
            'collection' => 'an object collection (`type: array` with `items.properties`)',
            default => sprintf('`type: %s`', is_scalar($property['type'] ?? null) ? (string)$property['type'] : 'unknown'),
        };
    }

    /**
     * @param array<string, mixed> $property
     *
     * @return string
     */
    protected function describeContributingFiles(array $property): string
    {
        $files = [];

        foreach ($property['_contributingFiles'] ?? [] as $entry) {
            if (is_array($entry) && isset($entry['file'])) {
                $files[] = $entry['file'];
            }
        }

        return $files === [] ? 'a lower layer' : implode(', ', array_unique($files));
    }

    /**
     * Deep-merges the nested `properties` (object) and `items.properties` (object collection) of a
     * property contributed by more than one schema, so their fields union instead of the later one
     * clobbering the earlier via the shallow {@see array_merge()} above.
     *
     * @param array<string, mixed> $baseProperty
     * @param array<string, mixed> $overrideProperty
     * @param array<string, mixed> $mergedProperty The shallow-merged property to refine.
     * @param array<string, mixed> $overrideSchema
     *
     * @return array<string, mixed>
     */
    protected function mergeNestedObjectProperties(array $baseProperty, array $overrideProperty, array $mergedProperty, array $overrideSchema): array
    {
        if (
            isset($baseProperty['properties'], $overrideProperty['properties'])
            && is_array($baseProperty['properties'])
            && is_array($overrideProperty['properties'])
        ) {
            $mergedProperty['properties'] = $this->mergeProperties(
                $baseProperty['properties'],
                $overrideProperty['properties'],
                $overrideSchema,
            );
        }

        if (
            isset($baseProperty['items']['properties'], $overrideProperty['items']['properties'])
            && is_array($baseProperty['items']['properties'])
            && is_array($overrideProperty['items']['properties'])
        ) {
            $mergedProperty['items']['properties'] = $this->mergeProperties(
                $baseProperty['items']['properties'],
                $overrideProperty['items']['properties'],
                $overrideSchema,
            );
        }

        return $mergedProperty;
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
     * @return array<string, mixed>
     */
    protected function createSourceInfo(array $schema): array
    {
        $layer = $schema['sourceLayer'] ?? 'unknown';

        if (!isset($schema['_layerSourceFiles']) || !is_array($schema['_layerSourceFiles'])) {
            return [
                'layer' => $layer,
                'files' => [$schema['sourceFile'] ?? 'unknown'],
            ];
        }

        return [
            'layer' => $layer,
            'files' => $schema['_layerSourceFiles'],
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
                $validation = $schema['validation'];
                $isMultiple = $this->isMultipleValidations($validation);

                if ($isMultiple) {
                    foreach ($validation as $validationSchema) {
                        $validationSchemas[] = $validationSchema;
                    }
                } else {
                    $validationSchemas[] = $validation;
                }
            }

            if (isset($schema['validationSourceFiles'])) {
                $validationSourceFiles = array_merge($validationSourceFiles, $schema['validationSourceFiles']);
            }
        }

        if ($validationSchemas !== []) {
            $mergedValidation = $this->validationSchemaMerger->merge($validationSchemas);

            $result['validation'] = $mergedValidation;
            $result['validationSourceFiles'] = array_unique($validationSourceFiles);
        }

        return $result;
    }

    protected function isMultipleValidations(mixed $validation): bool
    {
        if (!is_array($validation)) {
            return false;
        }

        if ($validation === []) {
            return false;
        }

        $firstKey = array_key_first($validation);

        return is_int($firstKey);
    }
}
