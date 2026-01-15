<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass that filters API Platform resources to only include the specified API type.
 *
 * This pass prevents cross-contamination when multiple API types have resources loaded
 * in PHP memory (Backend and Storefront). It removes any resource class definitions
 * that don't match the current API type pattern.
 *
 * When tests run without group filters, both Backend and Storefront resource classes
 * get loaded into PHP memory. Since PHP cannot unload classes, API Platform's
 * autoconfiguration discovers both types, causing route collisions. This pass ensures
 * only the correct API type resources are registered with API Platform by:
 * 1. Filtering resource_class_directories parameter
 * 2. Removing autoconfigured resource services that don't match the API type
 */
class FilterApiResourcesByTypePass implements CompilerPassInterface
{
    public function __construct(protected string $apiType)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $this->filterResourceDirectories($container);
        $this->removeNonMatchingApiTypeServices($container);
    }

    protected function filterResourceDirectories(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('api_platform.resource_class_directories')) {
            return;
        }

        $resourceDirs = $container->getParameter('api_platform.resource_class_directories');

        if (!is_array($resourceDirs)) {
            return;
        }

        $filteredDirs = [];

        foreach ($resourceDirs as $dir) {
            if ($this->isMatchingApiType($dir, $this->apiType)) {
                $filteredDirs[] = $dir;
            }
        }

        $container->setParameter('api_platform.resource_class_directories', $filteredDirs);
    }

    protected function removeNonMatchingApiTypeServices(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if ($class === null) {
                continue;
            }

            if ($this->isUnexpectedApiTypeProvider($class)) {
                $container->removeDefinition($id);
            }

            if ($this->isUnexpectedApiTypeProcessor($class)) {
                $container->removeDefinition($id);
            }

            if ($this->isUnexpectedApiTypeResource($class)) {
                $container->removeDefinition($id);
            }
        }
    }

    protected function isUnexpectedApiTypeProvider(string $class): bool
    {
        $expectedProviderNamespace = sprintf('%sProvider', ucfirst($this->apiType));

        // Skip everything that is not a provider and not from Spryker
        if (!str_ends_with($class, 'Provider') || !str_starts_with($class, 'Spryker\\')) {
            return false;
        }

        // If it is a provider but not from the expected API type, remove it
        if (!str_ends_with($class, $expectedProviderNamespace)) {
            return true;
        }

        return false;
    }

    protected function isUnexpectedApiTypeProcessor(string $class): bool
    {
        $expectedProcessorNamespace = sprintf('%sProcessor', ucfirst($this->apiType));

        // Skip everything that is not a processor and not from Spryker
        if (!str_ends_with($class, 'Processor') || !str_starts_with($class, 'Spryker\\')) {
            return false;
        }

        // If it is a processor but not from the expected API type, remove it
        if (!str_ends_with($class, $expectedProcessorNamespace)) {
            return true;
        }

        return false;
    }

    protected function isUnexpectedApiTypeResource(string $class): bool
    {
        $expectedResourceNamespace = sprintf('Generated\\Api\\%s\\', ucfirst($this->apiType));

        if (!str_starts_with($class, 'Generated\\Api\\')) {
            return false;
        }

        if (!str_starts_with($class, $expectedResourceNamespace)) {
            return true;
        }

        return false;
    }

    protected function isMatchingApiType(string $path, string $apiType): bool
    {
        return str_contains($path, sprintf('/Api/%s', ucfirst($apiType)));
    }
}
