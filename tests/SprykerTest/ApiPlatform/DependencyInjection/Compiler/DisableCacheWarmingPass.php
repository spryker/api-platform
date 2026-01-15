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
 * Compiler pass that disables API Platform cache warming services in the test environment.
 *
 * This is necessary because cache warming automatically generates all API resources
 * during kernel boot, which we want to control manually in tests to ensure proper
 * isolation between different API types (Backend vs Storefront).
 */
class DisableCacheWarmingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('cache_warmer')) {
            $container->removeDefinition('cache_warmer');
        }
        if ($container->hasDefinition('console.command.cache_warmup')) {
            $container->removeDefinition('console.command.cache_warmup');
        }
    }
}
