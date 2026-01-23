<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Metadata;

use ApiPlatform\Metadata\ResourceClassResolverInterface;

/**
 * Resolves resource classes with CodeBucket awareness.
 *
 * This resolver decorates the default ResourceClassResolver and adds runtime
 * resolution of CodeBucket-specific resource classes. When a resource class
 * is resolved, it checks if a CodeBucket-specific variant exists and returns
 * that instead of the base resource class.
 *
 * Resolution Strategy:
 * 1. Check if APPLICATION_CODE_BUCKET is set
 * 2. If set, look for a CodeBucket-specific resource class variant
 * 3. If variant exists and is a valid resource class, return it
 * 4. Otherwise, fall back to the base resource class (decorated resolver)
 *
 * Example:
 * - Base class: StoresBackendResource
 * - CodeBucket: EU
 * - Resolved to: StoresEUBackendResource (if it exists)
 * - Falls back to: StoresBackendResource (if EU variant doesn't exist)
 */
class CodeBucketResourceClassResolver implements ResourceClassResolverInterface
{
    use CodeBucketResolverTrait;

    /**
     * @var array<string, bool>
     */
    protected array $codeBucketResourceCache = [];

    public function __construct(
        protected readonly ResourceClassResolverInterface $decorated,
    ) {
    }

    public function getResourceClass(mixed $value, ?string $resourceClass = null, bool $strict = false): string
    {
        $resolvedClass = $this->decorated->getResourceClass($value, $resourceClass, $strict);

        $codeBucketVariant = $this->findCodeBucketVariant($resolvedClass);

        if ($codeBucketVariant !== null) {
            return $codeBucketVariant;
        }

        return $resolvedClass;
    }

    public function isResourceClass(string $type): bool
    {
        return $this->decorated->isResourceClass($type);
    }

    /**
     * Find CodeBucket-specific variant of a resource class if it exists.
     *
     * @param string $baseResourceClass The base resource class name
     *
     * @return string|null The CodeBucket-specific resource class if found, null otherwise
     */
    protected function findCodeBucketVariant(string $baseResourceClass): ?string
    {
        $codeBucket = $this->getCurrentCodeBucket();

        if ($codeBucket === '') {
            return null;
        }

        // If the given class is already a CodeBucket variant, check if it matches current CodeBucket
        if ($this->isCodeBucketResource($baseResourceClass)) {
            $classCodeBucket = $this->extractCodeBucketFromClass($baseResourceClass);

            if ($classCodeBucket === $codeBucket) {
                return $baseResourceClass;
            }

            $baseClass = $this->removeCodeBucketFromClassName($baseResourceClass);

            return $this->tryResolveCodeBucketVariant($baseClass, $codeBucket);
        }

        return $this->tryResolveCodeBucketVariant($baseResourceClass, $codeBucket);
    }

    /**
     * Try to resolve a CodeBucket-specific variant of a resource class.
     */
    protected function tryResolveCodeBucketVariant(string $baseResourceClass, string $codeBucket): ?string
    {
        $codeBucketClass = $this->buildCodeBucketClassName($baseResourceClass, $codeBucket);

        if ($this->isCodeBucketResourceClass($codeBucketClass)) {
            return $codeBucketClass;
        }

        return null;
    }

    /**
     * Check if a CodeBucket-specific class exists and is a valid resource class.
     */
    protected function isCodeBucketResourceClass(string $className): bool
    {
        if (isset($this->codeBucketResourceCache[$className])) {
            return $this->codeBucketResourceCache[$className];
        }

        if (!class_exists($className)) {
            return $this->codeBucketResourceCache[$className] = false;
        }

        if (!$this->decorated->isResourceClass($className)) {
            return $this->codeBucketResourceCache[$className] = false;
        }

        return $this->codeBucketResourceCache[$className] = true;
    }
}
