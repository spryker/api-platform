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
     */
    public function __construct(
        protected readonly array $sourceDirectories,
        protected readonly string $cacheDir,
        protected readonly string $generatedDir,
        protected readonly array $apiTypes,
        protected readonly bool $debug,
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
