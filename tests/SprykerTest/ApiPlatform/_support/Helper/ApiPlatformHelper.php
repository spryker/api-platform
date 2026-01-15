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
 * ```
 * - Cleans up container after each suite
 * - Generates resources dynamically in tests/_data/Api/
 * - For module-level testing in isolation
 */
class ApiPlatformHelper extends Module
{
    protected array $config = [
        'mode' => 'project',
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

    public function _afterSuite(): void
    {
        if ($this->isProjectMode()) {
            return;
        }

        $this->cleanupContainerCache();
    }

    /**
     * Checks if the helper is configured for project mode.
     */
    protected function isProjectMode(): bool
    {
        return TestMode::fromString($this->config['mode']) === TestMode::PROJECT;
    }

    /**
     * Cleans up the compiled container directory.
     */
    protected function cleanupContainerCache(): void
    {
        $containerDirectory = codecept_data_dir('symfony_test_kernel_cache');
        $logDirectory = codecept_data_dir('symfony_test_kernel_logs');

        $filesystem = new Filesystem();
        $filesystem->remove($containerDirectory);
        $filesystem->remove($logDirectory);
    }
}
