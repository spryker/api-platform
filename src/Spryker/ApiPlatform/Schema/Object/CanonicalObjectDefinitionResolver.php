<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Object;

use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;

/**
 * Resolves a list of canonical-object definition arrays (as produced by ObjectSchemaLoader)
 * into a flat per-object properties map.
 *
 * Two-pass algorithm:
 *   1. Group by `name`, merge across layers (core → feature → project, later wins per-key).
 *      `extends`/`omit` are taken from the highest layer that sets them.
 *   2. Topological `extends` resolution: start from the resolved base's properties,
 *      remove `omit` keys, then merge own `properties` on top.
 */
class CanonicalObjectDefinitionResolver
{
    /**
     * @var array<string, int> Ordered layer precedence (index = priority, higher = wins)
     */
    private const LAYER_ORDER = ['core' => 0, 'feature' => 1, 'project' => 2];

    /**
     * @param array<int, array<string, mixed>> $definitions
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function resolve(array $definitions): array
    {
        $merged = $this->mergeByName($definitions);

        return $this->resolveExtends($merged);
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     *
     * @return array<string, array<string, mixed>>
     */
    private function mergeByName(array $definitions): array
    {
        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];
        foreach ($definitions as $def) {
            if (!isset($def['name']) || !is_string($def['name']) || $def['name'] === '') {
                throw new ApiSchemaGenerationException('A canonical object definition is missing a non-empty string `name`.');
            }

            $name = $def['name'];
            $grouped[$name][] = $def;
        }

        $merged = [];
        foreach ($grouped as $name => $defs) {
            $this->assertNoDuplicateWithinLayer($name, $defs);

            usort($defs, function (array $a, array $b): int {
                $orderA = self::LAYER_ORDER[$a['layer'] ?? 'core'] ?? 0;
                $orderB = self::LAYER_ORDER[$b['layer'] ?? 'core'] ?? 0;

                return $orderA <=> $orderB;
            });

            $mergedProperties = [];
            $mergedExtends = null;
            $mergedOmit = [];

            foreach ($defs as $def) {
                /** @var array<string, array<string, mixed>> $props */
                $props = $def['properties'] ?? [];
                $mergedProperties = $this->mergeProperties($mergedProperties, $props);

                if (isset($def['extends']) && is_string($def['extends'])) {
                    $mergedExtends = $def['extends'];
                }
                if (isset($def['omit']) && is_array($def['omit'])) {
                    $mergedOmit = $def['omit'];
                }
            }

            $merged[$name] = [
                'name' => $name,
                'extends' => $mergedExtends,
                'omit' => $mergedOmit,
                'properties' => $mergedProperties,
            ];
        }

        return $merged;
    }

    /**
     * @param array<int, array<string, mixed>> $defs
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     */
    private function assertNoDuplicateWithinLayer(string $name, array $defs): void
    {
        /** @var array<string, array<int, string>> $sourceFilesByLayer */
        $sourceFilesByLayer = [];

        foreach ($defs as $def) {
            $layer = is_string($def['layer'] ?? null) ? $def['layer'] : 'core';
            $sourceFile = is_string($def['sourceFile'] ?? null) ? $def['sourceFile'] : '<unknown source>';
            $sourceFilesByLayer[$layer][] = $sourceFile;
        }

        foreach ($sourceFilesByLayer as $layer => $sourceFiles) {
            if (count($sourceFiles) < 2) {
                continue;
            }

            throw new ApiSchemaGenerationException(
                sprintf(
                    'Canonical object "%s" is defined more than once in the "%s" layer: %s. '
                    . 'Each object name may appear only once per layer; merge the definitions or move one to a different layer.',
                    $name,
                    $layer,
                    implode(', ', $sourceFiles),
                ),
            );
        }
    }

    /**
     * @param array<string, array<string, mixed>> $merged
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function resolveExtends(array $merged): array
    {
        /** @var array<string, array<string, mixed>> $resolved */
        $resolved = [];

        foreach (array_keys($merged) as $name) {
            $this->resolveOne($name, $merged, $resolved, [], '');
        }

        return $resolved;
    }

    /**
     * @param array<string, array<string, mixed>> $merged
     * @param array<string, array<string, mixed>> $resolved
     * @param array<string, true> $inProgress
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     */
    private function resolveOne(
        string $name,
        array $merged,
        array &$resolved,
        array $inProgress,
        string $requestedBy = '',
    ): void {
        if (array_key_exists($name, $resolved)) {
            return;
        }

        if (isset($inProgress[$name])) {
            throw new ApiSchemaGenerationException(
                sprintf(
                    'Circular extends detected for canonical object "%s". Cycle: %s → %s',
                    $name,
                    implode(' → ', array_keys($inProgress)),
                    $name,
                ),
            );
        }

        if (!array_key_exists($name, $merged)) {
            if ($requestedBy !== '') {
                throw new ApiSchemaGenerationException(
                    sprintf('Canonical object "%s" extends unknown object "%s".', $requestedBy, $name),
                );
            }

            return;
        }

        $def = $merged[$name];
        $inProgress[$name] = true;

        /** @var string|null $extends */
        $extends = $def['extends'] ?? null;
        /** @var array<string> $omit */
        $omit = $def['omit'] ?? [];
        /** @var array<string, array<string, mixed>> $ownProperties */
        $ownProperties = $def['properties'] ?? [];

        $baseProperties = [];
        if ($extends !== null) {
            $this->resolveOne($extends, $merged, $resolved, $inProgress, $name);
            $baseProperties = $resolved[$extends] ?? [];
        }

        // Remove omitted keys from base.
        foreach ($omit as $key) {
            unset($baseProperties[$key]);
        }

        // Merge own properties on top (own wins).
        $resolved[$name] = $this->mergeProperties($baseProperties, $ownProperties);
    }

    /**
     * @param array<string, array<string, mixed>> $base
     * @param array<string, array<string, mixed>> $override
     *
     * @return array<string, array<string, mixed>>
     */
    private function mergeProperties(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (isset($base[$key])) {
                /** @var array<string, mixed> $mergedValue */
                $mergedValue = array_merge($base[$key], $value);
                $base[$key] = $mergedValue;
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
