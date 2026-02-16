<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Metadata;

/**
 * Shared logic for CodeBucket resolution across different decorators.
 *
 * This trait provides common functionality for:
 * - Building CodeBucket-specific class names
 * - Extracting CodeBucket identifiers from class names
 * - Checking if a class is a CodeBucket resource
 * - Getting the current APPLICATION_CODE_BUCKET value
 */
trait CodeBucketResolverTrait
{
    /**
     * Build the CodeBucket-specific class name from a base class name.
     *
     * Pattern: {ResourceName}{CodeBucket}{ApiType}Resource
     * Example: StoresBackendResource + EU → StoresEUBackendResource
     */
    protected function buildCodeBucketClassName(string $baseResourceClass, string $codeBucket): string
    {
        if (str_contains($baseResourceClass, 'BackendResource')) {
            return str_replace('BackendResource', $codeBucket . 'BackendResource', $baseResourceClass);
        }

        if (str_contains($baseResourceClass, 'StorefrontResource')) {
            return str_replace('StorefrontResource', $codeBucket . 'StorefrontResource', $baseResourceClass);
        }

        return $baseResourceClass . $codeBucket;
    }

    /**
     * Remove CodeBucket from a class name to get the base class name.
     *
     * Example: StoresEUBackendResource → StoresBackendResource
     */
    protected function removeCodeBucketFromClassName(string $className): string
    {
        $codeBucket = $this->extractCodeBucketFromClass($className);

        if ($codeBucket === null) {
            return $className;
        }

        if (str_contains($className, $codeBucket . 'BackendResource')) {
            return str_replace($codeBucket . 'BackendResource', 'BackendResource', $className);
        }

        if (str_contains($className, $codeBucket . 'StorefrontResource')) {
            return str_replace($codeBucket . 'StorefrontResource', 'StorefrontResource', $className);
        }

        return $className;
    }

    /**
     * Check if a class name represents a CodeBucket-specific resource.
     */
    protected function isCodeBucketResource(string $className): bool
    {
        return $this->extractCodeBucketFromClass($className) !== null;
    }

    /**
     * Extract the CodeBucket identifier from a resource class name.
     *
     * Uses the CODE_BUCKET constant if available, otherwise returns null.
     */
    protected function extractCodeBucketFromClass(string $className): ?string
    {
        if (!class_exists($className)) {
            return null;
        }

        if (defined("$className::CODE_BUCKET")) {
            return constant("$className::CODE_BUCKET");
        }

        return null;
    }

    /**
     * Get the current CodeBucket from the APPLICATION_CODE_BUCKET constant.
     */
    protected function getCurrentCodeBucket(): string
    {
        if (!defined('APPLICATION_CODE_BUCKET')) {
            return '';
        }

        $codeBucket = APPLICATION_CODE_BUCKET;

        /** @phpstan-ignore function.alreadyNarrowedType */
        if (!is_string($codeBucket) || $codeBucket === '') {
            return '';
        }

        return $codeBucket;
    }
}
