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
use Spryker\ApiPlatform\State\OptionalFieldFilteringValidateProvider;
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
    protected const string SERVICE_ID_RESOURCE_CLASS_RESOLVER = 'api_platform.resource_class_resolver';

    protected const string SERVICE_ID_NAME_COLLECTION_FACTORY_CACHED = 'api_platform.metadata.resource.name_collection_factory.cached';

    protected const string SERVICE_ID_OPENAPI_FACTORY = 'api_platform.openapi.factory';

    protected const string SERVICE_ID_VALIDATE_STATE_PROVIDER = 'api_platform.state_provider.validate';

    protected const string TAG_FORMAT_TRANSFORMER = 'spryker_api_platform.format_transformer';

    protected const string REFERENCE_INNER = '.inner';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(static::SERVICE_ID_RESOURCE_CLASS_RESOLVER)) {
            return;
        }

        $container->register(CodeBucketResourceClassResolver::class, CodeBucketResourceClassResolver::class)
            ->setDecoratedService(static::SERVICE_ID_RESOURCE_CLASS_RESOLVER)
            ->setArguments([new Reference(static::REFERENCE_INNER)]);

        if ($container->has(static::SERVICE_ID_NAME_COLLECTION_FACTORY_CACHED)) {
            $container->register(CodeBucketResourceNameCollectionFactory::class, CodeBucketResourceNameCollectionFactory::class)
                ->setDecoratedService(static::SERVICE_ID_NAME_COLLECTION_FACTORY_CACHED)
                ->setArguments([new Reference(static::REFERENCE_INNER)]);
        }

        if ($container->has(static::SERVICE_ID_OPENAPI_FACTORY)) {
            $container->register(OpenApiDecorator::class, OpenApiDecorator::class)
                ->setDecoratedService(static::SERVICE_ID_OPENAPI_FACTORY)
                ->setArguments([
                    new Reference(static::REFERENCE_INNER),
                    new TaggedIteratorArgument(static::TAG_FORMAT_TRANSFORMER),
                ]);
        }

        if ($container->has(static::SERVICE_ID_VALIDATE_STATE_PROVIDER)) {
            $container->register(OptionalFieldFilteringValidateProvider::class, OptionalFieldFilteringValidateProvider::class)
                ->setDecoratedService(static::SERVICE_ID_VALIDATE_STATE_PROVIDER)
                ->setArguments([new Reference(static::REFERENCE_INNER)]);
        }
    }
}
