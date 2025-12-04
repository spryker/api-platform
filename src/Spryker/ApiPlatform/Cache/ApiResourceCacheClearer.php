<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Cache;

use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class ApiResourceCacheClearer implements CacheClearerInterface
{
    public function __construct(
        protected readonly ApiPlatformConfig $config,
        protected readonly Filesystem $filesystem,
    ) {
    }

    public function clear(string $cacheDir): void
    {
        $this->clearGeneratedDirectory();
    }

    protected function clearGeneratedDirectory(): void
    {
        $generatedResourcesDirectory = $this->config->getGeneratedResourcesDirectory();

        // All Generated files will be in he configured generated directory. Also the ones from other ApiTypes.
        // We have to make sure that we only remove the ones for the configured ApiTypes.
        foreach ($this->config->getApiTypes() as $apiType) {
            $directoryToRemove = sprintf('%s/%s', $generatedResourcesDirectory, ApiTypeNormalizer::normalizeForGeneration($apiType));

            if ($this->filesystem->exists($directoryToRemove)) {
                $this->filesystem->remove($directoryToRemove);
            }
        }
    }
}
