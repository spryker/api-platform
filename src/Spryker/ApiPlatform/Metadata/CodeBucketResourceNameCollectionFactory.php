<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;

/**
 * Filters resource name collection based on APPLICATION_CODE_BUCKET.
 *
 * This decorator intercepts the resource name collection and filters it to include
 * only the appropriate CodeBucket variants based on the current APPLICATION_CODE_BUCKET.
 *
 * Filtering Logic:
 * 1. If APPLICATION_CODE_BUCKET is set (e.g., 'EU'):
 *    - For each base resource, check if a CodeBucket variant exists
 *    - If variant exists (e.g., StoresEUBackendResource): include ONLY the variant
 *    - If no variant exists: include the base resource (graceful fallback)
 *    - Exclude all non-matching CodeBucket variants
 *
 * 2. If APPLICATION_CODE_BUCKET is not set:
 *    - Include all base resources (resources without CODE_BUCKET constant)
 *    - Exclude all CodeBucket variants (resources with CODE_BUCKET constant)
 *
 * This ensures:
 * - OpenAPI schema generation only sees relevant resources
 * - Route registration only creates routes for appropriate resources
 * - No conflicts between base and CodeBucket-specific resources
 */
class CodeBucketResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    use CodeBucketResolverTrait;

    public function __construct(
        protected readonly ResourceNameCollectionFactoryInterface $decorated,
    ) {
    }

    public function create(): ResourceNameCollection
    {
        $allResources = $this->decorated->create();
        $currentCodeBucket = $this->getCurrentCodeBucket();

        if ($currentCodeBucket === '') {
            $filteredResources = $this->filterForNoCodeBucket($allResources);

            return new ResourceNameCollection($filteredResources);
        }

        $filteredResources = $this->filterForCodeBucket($allResources, $currentCodeBucket);

        return new ResourceNameCollection($filteredResources);
    }

    /**
     * Filter resources when no APPLICATION_CODE_BUCKET is set.
     *
     * Strategy: Include only base resources, exclude all CodeBucket variants.
     *
     * @param \ApiPlatform\Metadata\Resource\ResourceNameCollection $resources All available resources
     *
     * @return array<string> Filtered resource class names
     */
    protected function filterForNoCodeBucket(ResourceNameCollection $resources): array
    {
        $filtered = [];

        foreach ($resources as $resourceClass) {
            if (!$this->isCodeBucketResource($resourceClass)) {
                $filtered[] = $resourceClass;
            }
        }

        return $filtered;
    }

    /**
     * Filter resources when APPLICATION_CODE_BUCKET is set.
     *
     * Strategy:
     * 1. Group resources by their base class name
     * 2. For each group, prefer CodeBucket variant over base resource
     * 3. If no matching variant exists, fall back to base resource
     *
     * @param \ApiPlatform\Metadata\Resource\ResourceNameCollection $resources All available resources
     * @param string $currentCodeBucket Current APPLICATION_CODE_BUCKET value
     *
     * @return array<string> Filtered resource class names
     */
    protected function filterForCodeBucket(ResourceNameCollection $resources, string $currentCodeBucket): array
    {
        $resourcesByBase = $this->groupResourcesByBaseClass($resources);
        $filtered = [];

        foreach ($resourcesByBase as $baseClassName => $variants) {
            $selectedResource = $this->selectResourceForCodeBucket($variants, $currentCodeBucket);

            if ($selectedResource !== null) {
                $filtered[] = $selectedResource;
            }
        }

        return $filtered;
    }

    /**
     * Group resource classes by their base class name.
     *
     * Example:
     * [
     *   'StoresBackendResource' => [
     *     'StoresBackendResource',
     *     'StoresEUBackendResource',
     *     'StoresATBackendResource',
     *   ],
     *   'ProductsBackendResource' => [
     *     'ProductsBackendResource',
     *   ]
     * ]
     *
     * @param \ApiPlatform\Metadata\Resource\ResourceNameCollection $resources All available resources
     *
     * @return array<string, array<string>> Resources grouped by base class
     */
    protected function groupResourcesByBaseClass(ResourceNameCollection $resources): array
    {
        $grouped = [];

        foreach ($resources as $resourceClass) {
            $baseClassName = $this->isCodeBucketResource($resourceClass)
                ? $this->removeCodeBucketFromClassName($resourceClass)
                : $resourceClass;

            if (!isset($grouped[$baseClassName])) {
                $grouped[$baseClassName] = [];
            }

            $grouped[$baseClassName][] = $resourceClass;
        }

        return $grouped;
    }

    /**
     * Select the appropriate resource for the current CodeBucket from variants.
     *
     * Priority:
     * 1. CodeBucket-specific variant that matches current APPLICATION_CODE_BUCKET
     * 2. Base resource (if no matching variant exists)
     *
     * @param array<string> $variants All variants for a base resource
     * @param string $currentCodeBucket Current APPLICATION_CODE_BUCKET value
     *
     * @return string|null Selected resource class, or null if none suitable
     */
    protected function selectResourceForCodeBucket(array $variants, string $currentCodeBucket): ?string
    {
        $baseResource = null;
        $matchingCodeBucketResource = null;

        foreach ($variants as $resourceClass) {
            if (!$this->isCodeBucketResource($resourceClass)) {
                $baseResource = $resourceClass;

                continue;
            }

            $resourceCodeBucket = $this->extractCodeBucketFromClass($resourceClass);

            if ($resourceCodeBucket === $currentCodeBucket) {
                $matchingCodeBucketResource = $resourceClass;

                break;
            }
        }

        return $matchingCodeBucketResource ?? $baseResource;
    }
}
