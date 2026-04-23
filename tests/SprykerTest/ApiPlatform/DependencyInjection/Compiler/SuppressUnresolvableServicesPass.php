<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\DependencyInjection\Compiler;

use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;

/**
 * Converts autowired services with unresolvable dependencies to non-autowired stubs.
 *
 * In core mode, not all Spryker services are available. This pass disables
 * autowiring on definitions whose constructor deps cannot be resolved,
 * preventing DefinitionErrorExceptionPass from throwing fatal errors.
 * The services become lazy stubs that only fail if actually instantiated.
 */
class SuppressUnresolvableServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definitionIds = array_keys($container->getDefinitions());

        foreach ($definitionIds as $serviceId) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            $definition = $container->getDefinition($serviceId);
            $className = $definition->getClass() ?? (string)$serviceId;

            if (!$definition->isAutowired() || !class_exists($className)) {
                continue;
            }

            if ($definition->getDecoratedService() !== null) {
                continue;
            }

            if ($this->hasUnresolvableDependency($container, $className)) {
                $definition->setAutowired(false);
                $definition->setArguments([]);
                $definition->setSynthetic(true);
            }
        }
    }

    protected function hasUnresolvableDependency(ContainerBuilder $container, string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return false;
            }

            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->isOptional()) {
                    continue;
                }

                $type = $parameter->getType();

                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $typeName = $type->getName();

                if ($container->has($typeName)) {
                    continue;
                }

                if (class_exists($typeName) && !interface_exists($typeName)) {
                    continue;
                }

                return true;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
