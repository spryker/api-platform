<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

use ApiPlatform\Symfony\Security\ResourceAccessChecker;
use Spryker\ApiPlatform\DependencyInjection\Compiler\ApiClassAutoDiscoveryPass;
use Spryker\ApiPlatform\DependencyInjection\Compiler\SchemaServiceRegistrationPass;
use SprykerTest\ApiPlatform\DependencyInjection\Compiler\FilterApiResourcesByTypePass;
use SprykerTest\ApiPlatform\DependencyInjection\Compiler\RegisterGeneratedResourcesPass;
use SprykerTest\ApiPlatform\DependencyInjection\Compiler\SuppressUnresolvableServicesPass;
use SprykerTest\ApiPlatform\Test\Security\CustomerProvider;
use SprykerTest\ApiPlatform\Test\Security\TokenAuthenticator;
use SprykerTest\Shared\Testify\Helper\Kernel\TestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ApiTestKernel extends TestKernel
{
    /**
     * @var array<string>
     */
    protected array $resourcePaths = [];

    protected string $apiType = '';

    /**
     * @var array<\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface>
     */
    protected array $testCompilerPasses = [];

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new SchemaServiceRegistrationPass());
        $container->addCompilerPass(new ApiClassAutoDiscoveryPass());

        if (TestModeConfiguration::isCoreMode()) {
            $container->addCompilerPass(new SuppressUnresolvableServicesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -100);
            $container->addCompilerPass(new FilterApiResourcesByTypePass($this->apiType));
            $container->addCompilerPass(new RegisterGeneratedResourcesPass($this->resourcePaths));
        }

        foreach ($this->testCompilerPasses as $pass) {
            $container->addCompilerPass($pass);
        }
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

    public function addTestCompilerPass(CompilerPassInterface $pass): static
    {
        $this->testCompilerPasses[] = $pass;

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

        $loader->load(function (ContainerBuilder $container): void {
            $this->registerSecurityServices($container);
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
     * Uses loadFromExtension so the SprykerApiPlatformExtension receives
     * these values during its load() method instead of having its defaults
     * overwrite raw setParameter() calls.
     */
    protected function configureCoreModeParameters(ContainerBuilder $container): void
    {
        $moduleRoot = $this->getProjectDir();

        $container->loadFromExtension('spryker_api_platform', [
            'source_directories' => [$moduleRoot],
            'api_types' => [$this->apiType],
            'cache_dir' => sprintf('%s/tests/_data/cache', $moduleRoot),
            'generated_dir' => sprintf('%s/tests/_data/Api', $moduleRoot),
            'debug' => true,
        ]);
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

    protected function registerSecurityServices(ContainerBuilder $container): void
    {
        $customerProviderDefinition = new Definition(CustomerProvider::class);
        $customerProviderDefinition->setPublic(true);
        $container->setDefinition(CustomerProvider::class, $customerProviderDefinition);

        $tokenAuthenticatorDefinition = new Definition(TokenAuthenticator::class);
        $tokenAuthenticatorDefinition->setArgument(0, $customerProviderDefinition);
        $tokenAuthenticatorDefinition->setPublic(true);
        $container->setDefinition(TokenAuthenticator::class, $tokenAuthenticatorDefinition);

        $this->registerApiPlatformSecurityServices($container);
    }

    protected function registerApiPlatformSecurityServices(ContainerBuilder $container): void
    {
        $resourceAccessCheckerDefinition = new Definition(ResourceAccessChecker::class);
        $resourceAccessCheckerDefinition->setArguments([
            new Reference('security.expression_language', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            new Reference('security.authentication.trust_resolver', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            new Reference('security.role_hierarchy', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            new Reference('security.token_storage', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            new Reference('security.authorization_checker', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
        ]);
        $resourceAccessCheckerDefinition->setPublic(true);
        $container->setDefinition('api_platform.security.resource_access_checker', $resourceAccessCheckerDefinition);
    }
}
