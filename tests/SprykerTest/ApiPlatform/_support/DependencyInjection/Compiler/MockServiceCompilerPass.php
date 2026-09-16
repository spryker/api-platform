<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Replaces mocked service definitions with synthetic definitions before container compilation.
 *
 * Synthetic services have no compiled factory — the container expects them to be provided
 * at runtime via `set()`. This allows test mocks to be injected into the compiled container
 * as if they were real services.
 */
class MockServiceCompilerPass implements CompilerPassInterface
{
    /**
     * @param array<string> $serviceIds
     */
    public function __construct(protected array $serviceIds)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($this->serviceIds as $serviceId) {
            if ($container->hasAlias($serviceId)) {
                $resolvedId = (string)$container->getAlias($serviceId);
                $this->makeServiceSynthetic($container, $resolvedId);

                continue;
            }

            $this->makeServiceSynthetic($container, $serviceId);
        }
    }

    protected function makeServiceSynthetic(ContainerBuilder $container, string $serviceId): void
    {
        $container->removeDefinition($serviceId);

        $definition = new Definition();
        $definition->setSynthetic(true);
        $definition->setPublic(true);
        $container->setDefinition($serviceId, $definition);
    }
}
