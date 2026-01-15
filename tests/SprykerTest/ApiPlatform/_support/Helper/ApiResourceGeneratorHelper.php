<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use RuntimeException;
use Spryker\ApiPlatform\Generator\ResourceGeneratorInterface;
use Spryker\ApiPlatform\SprykerApiPlatformBundle;
use Spryker\Shared\Kernel\Container\ContainerProxy;
use SprykerTest\ApiPlatform\Test\ApiTestKernel;
use SprykerTest\Shared\Testify\Helper\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Helper for API resource generation and cleanup.
 *
 * This utility provides functionality for generating and cleaning up API Platform resources
 * during test execution. It can be used directly from test cases via setUpBeforeClass
 * and tearDownAfterClass methods.
 *
 * Usage:
 * - generate(): Generates API resources for a specific API type
 * - cleanup(): Removes generated API resources and clears cache
 */
class ApiResourceGeneratorHelper
{
    /**
     * Generate API resources for a specific module and API type.
     *
     * @param string $moduleRoot Module root directory
     * @param string $apiType API type to generate
     */
    public function generate(string $moduleRoot, string $apiType): void
    {
        $this->ensureGeneratedDirectoryExists($moduleRoot, $apiType);

        $this->writeln(sprintf('Generating API resources for module: %s', basename($moduleRoot)));
        $this->writeln(sprintf('Module root: %s', $moduleRoot));

        $kernel = $this->bootTemporaryKernel($moduleRoot, $apiType);
        $container = $kernel->getContainer()->get('test.service_container');

        $this->writeln(sprintf('Source directories: %s', implode(', ', $container->getParameter('spryker_api_platform.source_directories'))));
        $this->writeln(sprintf('Generated dir: %s', $container->getParameter('spryker_api_platform.generated_dir')));

        $generator = $container->get(ResourceGeneratorInterface::class);

        $this->generateResources($generator, $apiType);

        $kernel->shutdown();

        $this->clearKernelCache($moduleRoot, $apiType);

        $this->writeln('API resource generation completed');
    }

    /**
     * Clean up generated API resources for a specific module and API type.
     *
     * @param string $moduleRoot Module root directory
     * @param string $apiType API type to clean up
     */
    public function cleanup(string $moduleRoot, string $apiType): void
    {
        $generatedDir = sprintf(
            '%s/tests/_data/Api',
            $moduleRoot,
        );

        if (!is_dir($generatedDir)) {
            return;
        }

        $this->writeln(sprintf('Cleaning up generated API resources: %s', $generatedDir));

        $filesystem = new Filesystem();
        $filesystem->remove($generatedDir);

        $this->clearKernelCache($moduleRoot, $apiType);
    }

    protected function ensureGeneratedDirectoryExists(string $moduleRoot, string $apiType): void
    {
        $filesystem = new Filesystem();

        $directories = [];
        $directories[] = sprintf('%s/tests/_data/Api', $moduleRoot);
        $directories[] = sprintf('%s/tests/_data/Api/%s', $moduleRoot, ucfirst($apiType));

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            try {
                $filesystem->mkdir($directory, 0755);
            } catch (IOException $e) {
                throw new RuntimeException(
                    sprintf('Failed to create directory "%s": %s', $directory, $e->getMessage()),
                    0,
                    $e,
                );
            }

            if (!is_writable($directory)) {
                throw new RuntimeException(
                    sprintf('Directory "%s" is not writable', $directory),
                );
            }
        }
    }

    protected function bootTemporaryKernel(string $moduleRoot, string $apiType): KernelInterface
    {
        $resourcePaths = $this->getResourcePaths($moduleRoot, $apiType);

        $kernel = new ApiTestKernel(new ContainerProxy(['test' => true]), true);

        // We only need the services from the SprykerApiPlatformBundle to be able to get the ResourceGenerator from the container
        // instead of constructing everything manually.
        $kernel->addBundles([
            FrameworkBundle::class,
            SprykerApiPlatformBundle::class,
        ]);

        $kernel->setResourcePaths($resourcePaths);
        $kernel->setApiType($apiType);
        $kernel->boot();

        return $kernel;
    }

    /**
     * @return array<string>
     */
    protected function getResourcePaths(string $moduleRoot, string $apiType): array
    {
        return [
            sprintf('%s/tests/_data/Api/%s', $moduleRoot, ucfirst($apiType)),
        ];
    }

    protected function generateResources(ResourceGeneratorInterface $generator, string $apiType): void
    {
        $this->writeln(sprintf('  Generating %s API resources...', $apiType));

        $errors = [];
        $generatedCount = 0;

        foreach ($generator->generateResources($apiType) as $result) {
            $status = $result['status'];

            if ($status === 'generated') {
                $generatedCount++;

                $resourceName = $result['resource'] ?? 'unknown';
                $this->writeln(sprintf('    - Generated: %s', $resourceName));
            }

            if ($status === 'error') {
                $errors[] = $result['message'] ?? 'Unknown error';
            }
        }

        if ($errors !== []) {
            $errorMessage = sprintf(
                "Failed to generate API resources for '%s':\n%s",
                $apiType,
                implode("\n", $errors),
            );

            throw new RuntimeException($errorMessage);
        }

        if ($generatedCount === 0) {
            $this->writeln(sprintf('    No %s API resources found to generate', $apiType));
        } else {
            $this->writeln(sprintf('    Generated %d %s API resource(s)', $generatedCount, $apiType));
        }
    }

    protected function clearKernelCache(string $moduleRoot, string $apiType): void
    {
        $baseCacheDir = TestKernel::getCacheDirPath($moduleRoot);
        $apiTypeCacheDir = sprintf('%s/%s', $baseCacheDir, strtolower($apiType));

        if (!is_dir($apiTypeCacheDir)) {
            return;
        }

        $this->writeln(sprintf('Clearing kernel cache: %s', $apiTypeCacheDir));

        $filesystem = new Filesystem();

        try {
            $filesystem->remove($apiTypeCacheDir);
        } catch (IOException $e) {
            $this->writeln(sprintf('Warning: Could not clear cache: %s', $e->getMessage()));
        }
    }

    protected function writeln(string $message): void
    {
        echo sprintf("[ApiResourceGenerator] %s\n", $message);
    }
}
