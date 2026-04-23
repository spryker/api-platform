<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Module;
use SprykerTest\ApiPlatform\Test\TestMode;
use SprykerTest\ApiPlatform\Test\TestModeConfiguration;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Helper for API Platform tests.
 *
 * Supports two modes configured via codeception.yml:
 *
 * Project mode (default):
 * ```yaml
 * modules:
 *   enabled:
 *     - \SprykerTest\ApiPlatform\Helper\ApiPlatformHelper:
 *         mode: 'project'
 * ```
 * - Skips container cleanup after suite (reuses compiled container)
 * - Uses pre-generated resources from src/Generated/Api/
 * - Faster test execution for project-level testing
 *
 * Core mode:
 * ```yaml
 * modules:
 *   enabled:
 *     - \SprykerTest\ApiPlatform\Helper\ApiPlatformHelper:
 *         mode: 'core'
 *         apiType: 'Storefront'
 * ```
 * - Cleans up container after each suite
 * - Generates resources dynamically in tests/_data/Api/
 * - For module-level testing in isolation
 */
class ApiPlatformHelper extends Module
{
    protected const string API_TYPE_STOREFRONT = 'Storefront';

    protected const string API_TYPE_BACKEND = 'Backend';

    protected array $config = [
        'mode' => 'project',
        'apiType' => '',
    ];

    /**
     * Called during module initialization.
     * Sets the test mode in TestModeConfiguration so it's available to test cases.
     */
    public function _initialize(): void
    {
        $mode = TestMode::fromString($this->config['mode']);
        TestModeConfiguration::setTestMode($mode);
    }

    public function _beforeSuite(array $settings = []): void
    {
        if ($this->isProjectMode()) {
            $this->validateProjectModeResources();

            return;
        }

        $apiType = $this->getApiType();

        if ($apiType === '') {
            return;
        }

        $moduleRoot = $this->resolveModuleRoot();

        $this->registerTestAutoloader($moduleRoot);

        $resourceHelper = new ApiResourceGeneratorHelper();
        $resourceHelper->cleanup($moduleRoot, $apiType);
        $resourceHelper->generate($moduleRoot, $apiType);
    }

    public function _afterSuite(): void
    {
        if ($this->isProjectMode()) {
            return;
        }

        $this->cleanupContainerCache();

        $apiType = $this->getApiType();

        if ($apiType === '') {
            return;
        }

        $moduleRoot = $this->resolveModuleRoot();
        $resourceHelper = new ApiResourceGeneratorHelper();
        $resourceHelper->cleanup($moduleRoot, $apiType);
    }

    protected function getApiType(): string
    {
        return $this->config['apiType'];
    }

    /**
     * Checks if the helper is configured for project mode.
     */
    protected function isProjectMode(): bool
    {
        return TestMode::fromString($this->config['mode']) === TestMode::PROJECT;
    }

    protected function resolveModuleRoot(): string
    {
        $dataDir = rtrim(codecept_data_dir(), DIRECTORY_SEPARATOR);

        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $dataDir = realpath($dataDir);

        return dirname($dataDir, 2);
    }

    protected function registerTestAutoloader(string $moduleRoot): void
    {
        $testResourceBasePath = sprintf('%s/tests/_data/Api', $moduleRoot);

        spl_autoload_register(
            function (string $className) use ($testResourceBasePath): void {
                if (!str_starts_with($className, 'Generated\\Api\\')) {
                    return;
                }

                $classNameWithoutPrefix = substr($className, strlen('Generated\\Api\\'));
                $filePath = sprintf(
                    '%s/%s.php',
                    $testResourceBasePath,
                    str_replace('\\', DIRECTORY_SEPARATOR, $classNameWithoutPrefix),
                );

                if (file_exists($filePath)) {
                    require_once $filePath;
                }
            },
            true,
            true,
        );
    }

    protected function validateProjectModeResources(): void
    {
        $apiType = $this->getApiType();

        if ($apiType === '') {
            return;
        }

        $projectRoot = defined('APPLICATION_ROOT_DIR')
            ? APPLICATION_ROOT_DIR
            : dirname(codecept_data_dir(), 3);

        $resourcePath = sprintf('%s/src/Generated/Api/%s', $projectRoot, $apiType);

        echo sprintf(
            "\n[ApiPlatformTest] Running in PROJECT mode for %s API.\n",
            $apiType,
        );

        if (!is_dir($resourcePath) || count(glob($resourcePath . '/*.php')) === 0) {
            echo sprintf(
                "[ApiPlatformTest] WARNING: No resources found at %s\n"
                . "[ApiPlatformTest] Generate resources with: vendor/bin/console api:generate %s\n\n",
                $resourcePath,
                strtolower($apiType),
            );

            return;
        }

        echo sprintf(
            "[ApiPlatformTest] Resources found at %s\n\n",
            $resourcePath,
        );
    }

    /**
     * Cleans up all test-generated directories and files.
     */
    protected function cleanupContainerCache(): void
    {
        $containerDirectory = codecept_data_dir('symfony_test_kernel_cache');
        $logDirectory = codecept_data_dir('symfony_test_kernel_logs');
        $cacheDirectory = codecept_data_dir('cache');
        $generatedApiDirectory = codecept_data_dir('Api');

        $filesystem = new Filesystem();
        $filesystem->remove($containerDirectory);
        $filesystem->remove($logDirectory);
        $filesystem->remove($cacheDirectory);
        $filesystem->remove($generatedApiDirectory);
    }
}
