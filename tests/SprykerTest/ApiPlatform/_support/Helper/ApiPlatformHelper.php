<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Module;
use Codeception\Test\TestCaseWrapper;
use Codeception\TestInterface;
use SprykerTest\ApiPlatform\Test\AbstractApiTestCase;
use SprykerTest\ApiPlatform\Test\TestMode;
use SprykerTest\ApiPlatform\Test\TestModeConfiguration;
use SprykerTest\Shared\Propel\Helper\TransactionHelper;
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
        'debug' => null,
        'bootOnce' => null,
        'reuseApplicationContainer' => null,
    ];

    /**
     * Called during module initialization.
     * Sets the test mode in TestModeConfiguration so it's available to test cases.
     *
     * The optional fast-path keys (debug/bootOnce/reuseApplicationContainer) are pushed into
     * TestModeConfiguration only when present; absent keys leave the historical
     * per-method-boot, debug-on behaviour untouched.
     */
    public function _initialize(): void
    {
        $mode = TestMode::fromString($this->config['mode']);
        TestModeConfiguration::setTestMode($mode);

        if ($this->config['debug'] !== null) {
            TestModeConfiguration::setDebug((bool)$this->config['debug']);
        }

        if ($this->config['bootOnce'] !== null) {
            TestModeConfiguration::setBootOnce((bool)$this->config['bootOnce']);
        }

        if ($this->config['reuseApplicationContainer'] !== null) {
            TestModeConfiguration::setReuseApplicationContainer((bool)$this->config['reuseApplicationContainer']);
        }
    }

    public function _before(TestInterface $test): void
    {
        if (!TestModeConfiguration::isBootOnce()) {
            return;
        }

        $testCase = $test instanceof TestCaseWrapper ? $test->getTestCase() : $test;

        if (!$testCase instanceof AbstractApiTestCase) {
            return;
        }

        $bootCountBeforeTest = AbstractApiTestCase::getBootCount();
        $testCase->getTestKernel();

        if (AbstractApiTestCase::getBootCount() === $bootCountBeforeTest) {
            return;
        }

        $this->reopenTestTransaction($test);
    }

    /**
     * The boot ran the application plugins, and `PropelApplicationPlugin` installed a fresh
     * connection manager: a transaction `TransactionHelper::_before()` opened BEFORE the boot lives
     * on the previous connection, while the test body and every request write on the new one — the
     * first test of the process would leak its rows. Re-opening the transaction on the connection
     * that is current after the boot keeps that test as isolated as all the following ones.
     */
    protected function reopenTestTransaction(TestInterface $test): void
    {
        $transactionHelperName = '\\' . TransactionHelper::class;

        if (!$this->hasModule($transactionHelperName)) {
            return;
        }

        /** @var \SprykerTest\Shared\Propel\Helper\TransactionHelper $transactionHelper */
        $transactionHelper = $this->getModule($transactionHelperName);
        $transactionHelper->_before($test);
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
        AbstractApiTestCase::resetSharedKernel();

        // Unconditional: `reuseApplicationContainer` keeps the compiled container (and the
        // ContainerDelegator's memoized services) alive between this suite's OWN test methods, but
        // that reuse must not survive the suite itself. Without this, a project-mode `run:filtered`
        // process that runs two suites with `reuseApplicationContainer` back to back (e.g. two
        // different modules' BackendApiIntegration suites) leaks the first suite's resolved
        // services — auth stubs included — into the second suite's requests.
        AbstractApiTestCase::resetContainerDelegator();

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

        $this->debugSection('ApiPlatform', sprintf('Running in PROJECT mode for %s API.', $apiType));

        if (!is_dir($resourcePath) || count(glob($resourcePath . '/*.php')) === 0) {
            // A warning, not a failure: the suite may only hold tests that never boot a kernel.
            $this->debugSection('ApiPlatform', sprintf(
                'No resources found at %s — generate them with: GLUE_APPLICATION=GLUE_%s vendor/bin/glue api:generate',
                $resourcePath,
                strtoupper($apiType),
            ));

            return;
        }

        $this->debugSection('ApiPlatform', sprintf('Resources found at %s', $resourcePath));
    }

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
