<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Configuration;

use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;

class ApiPlatformConfig
{
    /**
     * @param array<string> $sourceDirectories
     * @param array<string> $apiTypes
     * @param array<string> $excludedPathFragments
     * @param array<string, array<string>> $canonicalObjectSearchDirectories Map of apiType => list of absolute directories.
     */
    public function __construct(
        protected readonly array $sourceDirectories,
        protected readonly string $cacheDir,
        protected readonly string $generatedDir,
        protected readonly array $apiTypes,
        protected readonly bool $debug,
        protected readonly array $excludedPathFragments = [],
        protected readonly array $canonicalObjectSearchDirectories = [],
    ) {
    }

    /**
     * @api
     *
     * @return array<string>
     */
    public function getSourceDirectories(): array
    {
        return $this->sourceDirectories;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getGeneratedResourcesDirectory(): string
    {
        return $this->generatedDir;
    }

    /**
     * @api
     *
     * @return array<string>
     */
    public function getApiTypes(): array
    {
        return $this->apiTypes;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @api
     *
     * @return array<string>
     */
    public function getExcludedPathFragments(): array
    {
        return $this->excludedPathFragments;
    }

    /**
     * Additional ABSOLUTE directories to scan for canonical object files (`*.object.yml` /
     * `*.object.validation.yml`) for the given API type, on top of the in-module
     * `resources/api/<apiType>/objects/` locations.
     *
     * Files found here are always treated as the project layer (their path carries no `/Pyz/`
     * segment that path-based layer detection would key on), so they participate in the standard
     * project > feature > core merge precedence.
     *
     * The core default is an empty list — behavior is then byte-identical to scanning module
     * locations only. A project enables a central location via the Symfony bundle config node
     * `spryker_api_platform.canonical_object_search_directories`, keyed by API type:
     *
     *     spryker_api_platform:
     *         canonical_object_search_directories:
     *             storefront:
     *                 - '%kernel.project_dir%/config/api/objects/storefront'
     *
     * @api
     *
     * @return array<string> List of absolute directory paths.
     */
    public function getCanonicalObjectSearchDirectories(string $apiType): array
    {
        $apiType = ApiTypeNormalizer::normalizeForSchemaLookup($apiType);

        return $this->canonicalObjectSearchDirectories[$apiType] ?? [];
    }

    /**
     * Gets the resource directory for a specific API type.
     *
     * The API type is normalized to ucfirst format for proper directory structure
     * (e.g., Generated/Api/Backoffice/).
     *
     * @api
     *
     * @param string $apiType The API type (normalized to ucfirst automatically)
     *
     * @return string The absolute path to the API type resource directory
     */
    public function getApiResourceDirectory(string $apiType): string
    {
        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

        return sprintf('%s/%s', $this->generatedDir, $apiType);
    }
}
