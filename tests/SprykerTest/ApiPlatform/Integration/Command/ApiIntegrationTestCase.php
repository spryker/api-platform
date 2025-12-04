<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Command;

use Codeception\Test\Unit;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SprykerTest\ApiPlatform\ApiIntegrationTester;

/**
 * Base test case for API Platform integration tests.
 *
 * Use this class for tests that:
 * - Require Symfony kernel boot
 * - Test HTTP request or response cycles
 * - Verify API endpoint behavior
 * - Test code generation integration
 *
 * Available helpers via $this->tester:
 * - SymfonyApplicationHelper: Kernel and container access
 * - ApiPlatformHelper: API-specific assertions and utilities
 * - ApiSchemaHelper: Schema management and generation verification
 *
 * Example:
 * ```php
 * class CustomerApiTest extends ApiIntegrationTestCase
 * {
 *     public function testGivenCustomerWhenFetchingThenReturnsData(): void
 *     {
 *         // Arrange
 *         $this->createTestSchema('storefront', 'Customers', [...]);
 *
 *         // Act
 *         $response = $this->request('GET', '/api/storefront/customers');
 *
 *         // Assert
 *         $this->tester->assertResponseStatusCodeSame(200);
 *         $this->tester->assertJsonContains(['hydra:totalItems' => 1]);
 *     }
 * }
 * ```
 */
abstract class ApiIntegrationTestCase extends Unit
{
    protected ApiIntegrationTester $tester;

    protected function _before(): void
    {
        parent::_before();

        $this->tester->overrideConfigInContainer();
        $this->cleanupGeneratedFiles();
    }

    protected function _after(): void
    {
        $this->cleanupGeneratedFiles();

        parent::_after();
    }

    protected function getService(string $serviceId): object
    {
        return $this->tester->getService($serviceId);
    }

    /**
     * @param array<string, mixed> $options Request options
     */
    protected function request(string $method, string $uri, array $options = []): object
    {
        return $this->tester->request($method, $uri, $options);
    }

    /**
     * @param array<string, mixed> $schemaData The schema definition
     */
    protected function createTestSchema(
        string $apiType,
        string $resourceName,
        array $schemaData,
    ): string {
        return $this->tester->createSchemaFile($apiType, $resourceName, $schemaData);
    }

    protected function cleanupGeneratedFiles(): void
    {
        $config = $this->tester->getTestConfig();
        $generatedDir = $config->getGeneratedResourcesDirectory();

        if (!is_dir($generatedDir)) {
            return;
        }

        foreach ($config->getApiTypes() as $apiType) {
            $apiTypeDir = $config->getApiResourceDirectory($apiType);
            $this->removeDirectoryIfExists($apiTypeDir);
        }

        $this->cleanupCacheFiles();
    }

    protected function cleanupCacheFiles(): void
    {
        $config = $this->tester->getTestConfig();
        $cacheDir = $config->getCacheDir();

        if (!is_dir($cacheDir)) {
            return;
        }

        foreach ($config->getApiTypes() as $apiType) {
            $apiTypeCacheDir = sprintf('%s/%s', $cacheDir, ucfirst($apiType));
            $this->removeDirectoryIfExists($apiTypeCacheDir);
        }
    }

    protected function removeDirectoryIfExists(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $method = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $method($fileinfo->getRealPath());
        }

        rmdir($directory);
    }

    protected function getProjectRoot(): string
    {
        return codecept_root_dir();
    }
}
