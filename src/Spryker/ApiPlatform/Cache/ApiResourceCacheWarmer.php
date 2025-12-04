<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Cache;

use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Generator\ResourceGeneratorInterface;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class ApiResourceCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        protected readonly ResourceGeneratorInterface $resourceGenerator,
        protected readonly ApiPlatformConfig $config,
        protected readonly Filesystem $filesystem,
    ) {
    }

    /**
     * Warms up the cache by generating API resources.
     *
     * @return array<string>
     */
    public function warmUp(string $cacheDir): array
    {
        $warmedFiles = [];
        $apiTypes = $this->config->getApiTypes();

        foreach ($apiTypes as $apiType) {
            $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

            foreach ($this->resourceGenerator->generateResources($apiType) as $result) {
                if (isset($result['file'])) {
                    $warmedFiles[] = $result['file'];
                }
            }
        }

        // The ResourceGenerator cleans up the generated resource directories when generating resources.
        // When no Resources have been created, we have to make sure we do not break the ApiPlatform by ensuring the directories exist.
        $this->ensureGeneratedResourceDirectoriesExist();

        return $warmedFiles;
    }

    /**
     * Ensures that the generated resource directories exist for all API types.
     */
    protected function ensureGeneratedResourceDirectoriesExist(): void
    {
        foreach ($this->config->getApiTypes() as $apiType) {
            $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

            $outputDir = $this->config->getApiResourceDirectory($apiType);

            if (!$this->filesystem->exists($outputDir)) {
                $this->filesystem->mkdir($outputDir);
            }
        }
    }

    public function isOptional(): bool
    {
        return false;
    }
}
