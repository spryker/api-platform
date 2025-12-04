<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;

class ApiPlatformConfigBuilder
{
    protected const array DEFAULT_API_TYPES = ['backend', 'storefront'];

    protected string $sourceDir;

    protected string $cacheDir;

    protected string $generatedDir;

    protected array $apiTypes;

    protected bool $debug;

    public function __construct()
    {
        $dataDir = rtrim(codecept_data_dir(), DIRECTORY_SEPARATOR);

        $this->sourceDir = sprintf('%s', $dataDir);
        $this->cacheDir = sprintf('%s/cache', $dataDir);
        $this->generatedDir = sprintf('%s/generated', $dataDir);
        $this->apiTypes = static::DEFAULT_API_TYPES;
        $this->debug = true;
    }

    public function build(): ApiPlatformConfig
    {
        $this->ensureDirectoriesExist();

        return new ApiPlatformConfig(
            sourceDirectories: [$this->sourceDir],
            cacheDir: $this->cacheDir,
            generatedDir: $this->generatedDir,
            apiTypes: $this->apiTypes,
            debug: $this->debug,
        );
    }

    protected function ensureDirectoriesExist(): void
    {
        $directories = [
            $this->sourceDir,
            $this->cacheDir,
            $this->generatedDir,
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }
}
