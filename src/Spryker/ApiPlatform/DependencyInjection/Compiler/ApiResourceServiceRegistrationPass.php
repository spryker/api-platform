<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Automatically registers processor and provider services from API resource schemas.
 *
 * This compiler pass scans all resource schema files (*.resource.yml/yaml) across
 * configured API types and automatically registers any processor or provider classes
 * as public services in the DI container.
 *
 * This eliminates the need for manual service registration in project-level
 * configuration files (e.g., config/GlueBackend/ApplicationServices.php).
 */
class ApiResourceServiceRegistrationPass implements CompilerPassInterface
{
    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$this->hasRequiredParameters($container)) {
            return;
        }

        $apiTypes = $container->getParameter('spryker_api_platform.api_types');

        if (!is_array($apiTypes) || $apiTypes === []) {
            return;
        }

        $sourceDirectories = $container->getParameter('spryker_api_platform.source_directories');

        if (!is_array($sourceDirectories)) {
            return;
        }

        $registeredServices = [];

        foreach ($apiTypes as $apiType) {
            $schemaFiles = $this->findSchemaFiles($sourceDirectories, $apiType);

            foreach ($schemaFiles as $schemaFile) {
                $services = $this->extractServicesFromSchema($schemaFile);

                foreach ($services as $serviceClass) {
                    if ($this->shouldRegisterService($container, $serviceClass, $registeredServices)) {
                        $this->registerService($container, $serviceClass);
                    }
                }
            }
        }
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *
     * @return bool
     */
    protected function hasRequiredParameters(ContainerBuilder $container): bool
    {
        return $container->hasParameter('spryker_api_platform.api_types')
            && $container->hasParameter('spryker_api_platform.source_directories');
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<\SplFileInfo>
     */
    protected function findSchemaFiles(array $sourceDirectories, string $apiType): array
    {
        $apiType = strtolower($apiType);

        $schemaFiles = [];
        $extensions = ['resource.yaml', 'resource.yml'];

        foreach ($sourceDirectories as $sourceDirectory) {
            if (!is_dir($sourceDirectory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();

                if (!str_contains($path, sprintf('/resources/api/%s/', $apiType))) {
                    continue;
                }

                foreach ($extensions as $extension) {
                    if (str_ends_with($path, '.' . $extension)) {
                        $schemaFiles[] = $file;

                        break;
                    }
                }
            }
        }

        return $schemaFiles;
    }

    /**
     * @return array<string>
     */
    protected function extractServicesFromSchema(SplFileInfo $schemaFile): array
    {
        $services = [];

        try {
            $schema = Yaml::parseFile($schemaFile->getPathname());

            if (!is_array($schema)) {
                return [];
            }

            if (isset($schema['resource']['provider']) && is_string($schema['resource']['provider'])) {
                $services[] = $schema['resource']['provider'];
            }

            if (isset($schema['resource']['processor']) && is_string($schema['resource']['processor'])) {
                $services[] = $schema['resource']['processor'];
            }
        } catch (Throwable $e) {
        }

        return $services;
    }

    /**
     * @param array<string> $registeredServices
     */
    protected function shouldRegisterService(
        ContainerBuilder $container,
        string $serviceClass,
        array $registeredServices,
    ): bool {
        if (in_array($serviceClass, $registeredServices, true)) {
            return false;
        }

        if ($container->has($serviceClass)) {
            return false;
        }

        if (!class_exists($serviceClass)) {
            return false;
        }

        return true;
    }

    protected function registerService(ContainerBuilder $container, string $serviceClass): void
    {
        $definition = new Definition($serviceClass);
        $definition->setPublic(true);
        $definition->setAutowired(true);
        $definition->setAutoconfigured(true);

        // Determine if provider or processor and tag accordingly
        if (is_subclass_of($serviceClass, ProviderInterface::class)) {
            $definition->addTag('api_platform.state_provider', ['key' => $serviceClass]);
        }

        if (is_subclass_of($serviceClass, ProcessorInterface::class)) {
            $definition->addTag('api_platform.state_processor', ['key' => $serviceClass]);
        }

        $container->setDefinition($serviceClass, $definition);
    }
}
