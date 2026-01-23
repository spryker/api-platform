<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use Spryker\ApiPlatform\Metadata\CodeBucketResourceClassResolver;
use Spryker\ApiPlatform\Metadata\CodeBucketResourceNameCollectionFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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
class ApiPlatformDecoratorPass implements CompilerPassInterface
{
    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('api_platform.resource_class_resolver')) {
            return;
        }

        $container->register(CodeBucketResourceClassResolver::class, CodeBucketResourceClassResolver::class)
            ->setDecoratedService('api_platform.resource_class_resolver')
            ->setArguments([new Reference('.inner')]);

        if ($container->has('api_platform.metadata.resource.name_collection_factory.cached')) {
            $container->register(CodeBucketResourceNameCollectionFactory::class, CodeBucketResourceNameCollectionFactory::class)
                ->setDecoratedService('api_platform.metadata.resource.name_collection_factory.cached')
                ->setArguments([new Reference('.inner')]);
        }
    }
}
