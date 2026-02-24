<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use Spryker\ApiPlatform\Metadata\CodeBucketResourceClassResolver;
use Spryker\ApiPlatform\Metadata\CodeBucketResourceNameCollectionFactory;
use Spryker\ApiPlatform\OpenApi\Decorator\OpenApiDecorator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers decorators for API Platform services with existence checks.
 *
 * This compiler pass conditionally decorates API Platform services only if they exist
 * in the container. This allows the bundle to work in testing environments where
 * API Platform might not be fully loaded.
 *
 * Decorators registered:
 * - CodeBucketResourceClassResolver: Adds CodeBucket support to resource class resolution
 * - CodeBucketResourceNameCollectionFactory: Adds CodeBucket support to resource name collection
 * - OpenApiDecorator: Applies format-specific transformations to OpenAPI documentation
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

        if ($container->has('api_platform.openapi.factory')) {
            $container->register(OpenApiDecorator::class, OpenApiDecorator::class)
                ->setDecoratedService('api_platform.openapi.factory')
                ->setArguments([
                    new Reference('.inner'),
                    new TaggedIteratorArgument('spryker_api_platform.format_transformer'),
                ]);
        }
    }
}
