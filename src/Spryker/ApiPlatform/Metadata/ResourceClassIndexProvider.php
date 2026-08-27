<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Metadata;

use Spryker\ApiPlatform\DependencyInjection\Compiler\ResourceClassIndexPass;

/**
 * Resolves the resource short name to resource class index for a code bucket from the
 * compile-time index parameter
 * ({@see \Spryker\ApiPlatform\DependencyInjection\Compiler\ResourceClassIndexPass}).
 */
class ResourceClassIndexProvider implements ResourceClassIndexProviderInterface
{
    /**
     * @var array<string, array{classIndex: array<string, class-string>, priorityIndex: array<string, int>}>
     */
    protected array $resolvedIndexes = [];

    /**
     * @param array<class-string, array<string, array{shortName: string, className: class-string, includedSortPriority: int|null}>> $resourceClassIndex
     */
    public function __construct(protected readonly array $resourceClassIndex)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getResourceClassIndex(string $codeBucket): array
    {
        return $this->resolveIndexes($codeBucket)['classIndex'];
    }

    /**
     * {@inheritDoc}
     */
    public function getIncludedSortPriorityIndex(string $codeBucket): array
    {
        return $this->resolveIndexes($codeBucket)['priorityIndex'];
    }

    /**
     * @return array{classIndex: array<string, class-string>, priorityIndex: array<string, int>}
     */
    protected function resolveIndexes(string $codeBucket): array
    {
        if (isset($this->resolvedIndexes[$codeBucket])) {
            return $this->resolvedIndexes[$codeBucket];
        }

        $classIndex = [];
        $priorityIndex = [];

        foreach ($this->resourceClassIndex as $indexEntriesByCodeBucket) {
            $indexEntry = $indexEntriesByCodeBucket[$codeBucket]
                ?? $indexEntriesByCodeBucket[ResourceClassIndexPass::CODE_BUCKET_KEY_BASE]
                ?? null;

            if ($indexEntry === null) {
                continue;
            }

            $classIndex[$indexEntry['shortName']] = $indexEntry['className'];

            if ($indexEntry['includedSortPriority'] !== null) {
                $priorityIndex[$indexEntry['shortName']] = $indexEntry['includedSortPriority'];

                continue;
            }

            // On a short name collision both maps must reflect the same winning entry — a stale
            // priority from an earlier same-named entry may not outlive its class mapping.
            unset($priorityIndex[$indexEntry['shortName']]);
        }

        return $this->resolvedIndexes[$codeBucket] = ['classIndex' => $classIndex, 'priorityIndex' => $priorityIndex];
    }
}
