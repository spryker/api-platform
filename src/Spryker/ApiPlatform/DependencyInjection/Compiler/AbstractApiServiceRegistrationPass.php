<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

abstract class AbstractApiServiceRegistrationPass implements CompilerPassInterface
{
    /**
     * @var array<string>
     */
    protected array $registeredServices = [];

    protected function hasRequiredParameters(ContainerBuilder $container): bool
    {
        return $container->hasParameter('spryker_api_platform.api_types')
            && $container->hasParameter('spryker_api_platform.source_directories');
    }

    /**
     * @return array{apiTypes: array<string>, sourceDirectories: array<string>}|null
     */
    protected function resolveParameters(ContainerBuilder $container): ?array
    {
        if (!$this->hasRequiredParameters($container)) {
            return null;
        }

        $apiTypes = $container->getParameter('spryker_api_platform.api_types');

        if (!is_array($apiTypes) || $apiTypes === []) {
            return null;
        }

        $sourceDirectories = $container->getParameter('spryker_api_platform.source_directories');

        if (!is_array($sourceDirectories)) {
            return null;
        }

        return ['apiTypes' => $apiTypes, 'sourceDirectories' => $sourceDirectories];
    }

    protected function shouldRegisterService(
        ContainerBuilder $container,
        string $serviceClass,
    ): bool {
        if (in_array($serviceClass, $this->registeredServices, true)) {
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

        if (is_subclass_of($serviceClass, ProviderInterface::class)) {
            $definition->addTag('api_platform.state_provider', ['key' => $serviceClass]);
        }

        if (is_subclass_of($serviceClass, ProcessorInterface::class)) {
            $definition->addTag('api_platform.state_processor', ['key' => $serviceClass]);
        }

        if (is_subclass_of($serviceClass, Voter::class)) {
            $definition->addTag('security.voter');
        }

        $this->applyAutoconfigurationTags($container, $serviceClass, $definition);

        $container->setDefinition($serviceClass, $definition);
        $this->registeredServices[] = $serviceClass;
    }

    /**
     * Applies tags from registered autoconfiguration rules to the service definition.
     * This bridges the gap between `registerForAutoconfiguration()` (called in extensions)
     * and services registered by this compiler pass (which bypass standard autoconfiguration).
     */
    protected function applyAutoconfigurationTags(
        ContainerBuilder $container,
        string $serviceClass,
        Definition $definition,
    ): void {
        foreach ($container->getAutoconfiguredInstanceof() as $interface => $childDefinition) {
            if (!is_subclass_of($serviceClass, $interface)) {
                continue;
            }

            foreach ($childDefinition->getTags() as $tag => $attributes) {
                foreach ($attributes as $tagAttributes) {
                    $definition->addTag($tag, $tagAttributes);
                }
            }
        }
    }

    protected function resolveClassNameFromFile(SplFileInfo $file): ?string
    {
        $content = file_get_contents($file->getPathname());

        if ($content === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/(?:class|interface)\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($namespace === null || $class === null) {
            return null;
        }

        return sprintf('%s\\%s', $namespace, $class);
    }
}
