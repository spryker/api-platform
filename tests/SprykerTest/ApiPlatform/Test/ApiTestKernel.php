<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

use Spryker\ApiPlatform\DependencyInjection\Compiler\ApiResourceServiceRegistrationPass;
use Spryker\Service\Container\Pass\SprykerDefaultsPass;
use SprykerTest\ApiPlatform\DependencyInjection\Compiler\DisableCacheWarmingPass;
use SprykerTest\ApiPlatform\DependencyInjection\Compiler\FilterApiResourcesByTypePass;
use SprykerTest\ApiPlatform\DependencyInjection\Compiler\RegisterGeneratedResourcesPass;
use SprykerTest\Shared\Testify\Helper\Kernel\TestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ApiTestKernel extends TestKernel
{
    /**
     * @var array<string>
     */
    protected array $resourcePaths = [];

    protected string $apiType = '';

    protected function build(ContainerBuilder $container): void
    {
        if (TestModeConfiguration::isProjectMode()) {
            parent::build($container);

            return;
        }

        $container->addCompilerPass(new SprykerDefaultsPass());
        $container->addCompilerPass(new ApiResourceServiceRegistrationPass());
        $container->addCompilerPass(new DisableCacheWarmingPass());
        $container->addCompilerPass(new FilterApiResourcesByTypePass($this->apiType));
        $container->addCompilerPass(new RegisterGeneratedResourcesPass($this->resourcePaths));
    }

    public function setResourcePaths(array $resourcePaths): self
    {
        $this->resourcePaths = $resourcePaths;

        return $this;
    }

    public function setApiType(string $apiType): self
    {
        $this->apiType = $apiType;

        return $this;
    }

    public function getCacheDir(): string
    {
        if (TestModeConfiguration::isProjectMode()) {
            return parent::getCacheDir();
        }

        return $this->getCoreModeCacheDir();
    }

    /**
     * Returns the cache directory for core mode.
     */
    protected function getCoreModeCacheDir(): string
    {
        $baseDir = parent::getCacheDir();

        return sprintf('%s/%s', $baseDir, strtolower($this->apiType));
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        if (TestModeConfiguration::isProjectMode()) {
            parent::registerContainerConfiguration($loader);

            return;
        }

        $loader->load(function (ContainerBuilder $container): void {
            $container->setParameter('kernel.project_dir', $this->getProjectDir());
        });

        $loader->load(function (ContainerBuilder $container): void {
            $this->configureApiPlatformContainer($container);
        });
    }

    protected function configureApiPlatformContainer(ContainerBuilder $container): void
    {
        $container->setParameter('kernel.project_dir', $this->getProjectDir());

        $this->configureSprykerApiPlatformParameters($container);

        $frameworkConfig = [
            'secret' => 'test_secret',
            'test' => true,
            'http_method_override' => false,
            'router' => [
                'utf8' => true,
                'resource' => 'api_platform',
                'type' => 'api_platform',
            ],
        ];

        if (isset($this->bundleConfigurations['framework'])) {
            $frameworkConfig = $this->bundleConfigurations['framework'];
            unset($this->bundleConfigurations['framework']);
        }

        if ($frameworkConfig) {
            $container->loadFromExtension('framework', $frameworkConfig);
        }

        foreach ($this->bundleConfigurations as $bundleName => $configuration) {
            $container->loadFromExtension($bundleName, $configuration);
        }

        $container->setParameter('kernel.bundles', $this->bundleClasses);
    }

    protected function configureSprykerApiPlatformParameters(ContainerBuilder $container): void
    {
        if (TestModeConfiguration::isProjectMode()) {
            return;
        }

        $this->configureCoreModeParameters($container);
    }

    /**
     * Configures parameters for core mode.
     *
     * Uses module-level test paths for resources and cache.
     */
    protected function configureCoreModeParameters(ContainerBuilder $container): void
    {
        $moduleRoot = $this->getProjectDir();

        $container->setParameter('spryker_api_platform.api_types', [$this->apiType]);
        $container->setParameter('spryker_api_platform.source_directories', [$moduleRoot]);
        $container->setParameter('spryker_api_platform.cache_dir', sprintf('%s/tests/_data/cache', $moduleRoot));
        $container->setParameter('spryker_api_platform.generated_dir', sprintf('%s/tests/_data/Api', $moduleRoot));
        $container->setParameter('spryker_api_platform.debug', true);
    }

    public function getProjectDir(): string
    {
        if (TestModeConfiguration::isProjectMode()) {
            return APPLICATION_ROOT_DIR;
        }

        $dataDir = realpath(rtrim(codecept_data_dir(), DIRECTORY_SEPARATOR));

        return dirname($dataDir, 2);
    }

    protected function getContainerClass(): string
    {
        $parentClass = parent::getContainerClass();

        // Include API type in container class name to prevent pollution between Backend/Storefront
        return $parentClass . '_' . $this->apiType;
    }
}
