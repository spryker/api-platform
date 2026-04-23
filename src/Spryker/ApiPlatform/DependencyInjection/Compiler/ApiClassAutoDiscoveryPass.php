<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

/**
 * Discovers and registers PHP classes under Api/{apiType}/ directories in Glue layers.
 *
 * Ensures mappers, resolvers, and other support classes used by providers/processors
 * are available as autowired services. Also creates interface-to-implementation aliases
 * for discovered interfaces.
 */
class ApiClassAutoDiscoveryPass extends AbstractApiServiceRegistrationPass
{
    /**
     * @var array<string>
     */
    protected array $discoveredInterfaces = [];

    public function process(ContainerBuilder $container): void
    {
        $parameters = $this->resolveParameters($container);

        if ($parameters === null) {
            return;
        }

        foreach ($parameters['apiTypes'] as $apiType) {
            $this->discoverApiClasses($container, $parameters['sourceDirectories'], $apiType);
        }
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return void
     */
    protected function discoverApiClasses(
        ContainerBuilder $container,
        array $sourceDirectories,
        string $apiType,
    ): void {
        $apiTypePascalCase = ucfirst($apiType);

        foreach ($sourceDirectories as $sourceDirectory) {
            if (!is_dir($sourceDirectory)) {
                continue;
            }

            try {
                $finder = new Finder();
                $finder->files()
                    ->in($sourceDirectory)
                    ->name('*.php')
                    ->path(sprintf('#Glue/.+/Api/%s/#', $apiTypePascalCase))
                    ->notPath('#(?:^|/)Provider/#')
                    ->notPath('#(?:^|/)Processor/#');

                foreach ($finder as $file) {
                    $className = $this->resolveClassNameFromFile($file);

                    if ($className === null) {
                        continue;
                    }

                    if (interface_exists($className)) {
                        $this->discoveredInterfaces[] = $className;

                        continue;
                    }

                    if ($this->shouldRegisterService($container, $className)) {
                        $this->registerService($container, $className);
                    }
                }
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        $this->registerInterfaceAliases($container);
    }

    /**
     * Creates interface-to-implementation aliases for discovered interfaces
     * whose implementations were registered as services.
     *
     * @return void
     */
    protected function registerInterfaceAliases(ContainerBuilder $container): void
    {
        foreach ($this->discoveredInterfaces as $interfaceName) {
            if ($container->has($interfaceName)) {
                continue;
            }

            $implementationClass = $this->findRegisteredImplementation($container, $interfaceName);

            if ($implementationClass === null) {
                continue;
            }

            $container->setAlias($interfaceName, $implementationClass)->setPublic(true);
        }

        $this->discoveredInterfaces = [];
    }

    protected function findRegisteredImplementation(ContainerBuilder $container, string $interfaceName): ?string
    {
        foreach ($this->registeredServices as $serviceClass) {
            if (is_subclass_of($serviceClass, $interfaceName)) {
                return $serviceClass;
            }
        }

        return null;
    }
}
