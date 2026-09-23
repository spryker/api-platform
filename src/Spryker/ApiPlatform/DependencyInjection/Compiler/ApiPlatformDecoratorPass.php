<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use Spryker\ApiPlatform\JsonSchema\JsonApiInputSchemaFactory;
use Spryker\ApiPlatform\Metadata\CodeBucketResourceClassResolver;
use Spryker\ApiPlatform\Metadata\CodeBucketResourceNameCollectionFactory;
use Spryker\ApiPlatform\OpenApi\Decorator\ErrorResponseOpenApiDecorator;
use Spryker\ApiPlatform\OpenApi\Decorator\OpenApiDecorator;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ApiPlatformErrorSchemaRemover;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\DefaultErrorResponseAdder;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseDocumenter;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\GlueApiErrorSchema;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\OperationMetadataResolver;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\PathItemOperationAccessor;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\RequestAttributesResolver;
use Spryker\ApiPlatform\State\OptionalFieldFilteringValidateProvider;
use Spryker\ApiPlatform\State\StrictBooleanCanonicalizingDeserializeProvider;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
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
 * - ErrorResponseOpenApiDecorator: Documents every error response with the Glue error schema and examples
 * - OptionalFieldFilteringValidateProvider: Drops violations for Optional fields absent from the body
 * - StrictBooleanCanonicalizingDeserializeProvider: Re-applies the boolean a client spelled as a string
 */
class ApiPlatformDecoratorPass implements CompilerPassInterface
{
    protected const string SERVICE_ID_RESOURCE_CLASS_RESOLVER = 'api_platform.resource_class_resolver';

    protected const string SERVICE_ID_NAME_COLLECTION_FACTORY_CACHED = 'api_platform.metadata.resource.name_collection_factory.cached';

    protected const string SERVICE_ID_OPENAPI_FACTORY = 'api_platform.openapi.factory';

    protected const string SERVICE_ID_VALIDATE_STATE_PROVIDER = 'api_platform.state_provider.validate';

    protected const string SERVICE_ID_SCHEMA_FACTORY = 'api_platform.json_schema.backward_compatible_schema_factory';

    protected const string SERVICE_ID_DESERIALIZE_STATE_PROVIDER = 'api_platform.state_provider.deserialize';

    protected const string TAG_FORMAT_TRANSFORMER = 'spryker_api_platform.format_transformer';

    protected const string REFERENCE_INNER = '.inner';

    /**
     * Lower than the default priority of {@see OpenApiDecorator}, so this decorator is applied last and wraps
     * the format decorator: it documents the error responses of the finished document.
     */
    protected const int DECORATION_PRIORITY_ERROR_RESPONSES = -10;

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
                ->setArguments([
                    new Reference(static::REFERENCE_INNER),
                    new Parameter(ResourceClassIndexPass::PARAMETER_RESOURCE_CLASS_INDEX),
                ]);
        }

        if ($container->has(static::SERVICE_ID_OPENAPI_FACTORY)) {
            $container->register(OpenApiDecorator::class, OpenApiDecorator::class)
                ->setDecoratedService(static::SERVICE_ID_OPENAPI_FACTORY)
                ->setArguments([
                    new Reference(static::REFERENCE_INNER),
                    new TaggedIteratorArgument(static::TAG_FORMAT_TRANSFORMER),
                ]);

            $container->register(ErrorResponseOpenApiDecorator::class, ErrorResponseOpenApiDecorator::class)
                ->setDecoratedService(static::SERVICE_ID_OPENAPI_FACTORY, null, static::DECORATION_PRIORITY_ERROR_RESPONSES)
                ->setArguments([
                    new Reference(static::REFERENCE_INNER),
                    new Reference(OperationMetadataResolver::class),
                    new Reference(PathItemOperationAccessor::class),
                    new Reference(DefaultErrorResponseAdder::class),
                    new Reference(RequestAttributesResolver::class),
                    new Reference(ErrorResponseDocumenter::class),
                    new Reference(ApiPlatformErrorSchemaRemover::class),
                    new Reference(GlueApiErrorSchema::class),
                ]);
        }

        if ($container->has(static::SERVICE_ID_SCHEMA_FACTORY)) {
            $container->register(JsonApiInputSchemaFactory::class, JsonApiInputSchemaFactory::class)
                ->setDecoratedService(static::SERVICE_ID_SCHEMA_FACTORY)
                ->setArguments([new Reference(static::REFERENCE_INNER)]);
        }

        if ($container->has(static::SERVICE_ID_VALIDATE_STATE_PROVIDER)) {
            $container->register(OptionalFieldFilteringValidateProvider::class, OptionalFieldFilteringValidateProvider::class)
                ->setDecoratedService(static::SERVICE_ID_VALIDATE_STATE_PROVIDER)
                ->setArguments([new Reference(static::REFERENCE_INNER)]);
        }

        if ($container->has(static::SERVICE_ID_DESERIALIZE_STATE_PROVIDER)) {
            $container->register(
                StrictBooleanCanonicalizingDeserializeProvider::class,
                StrictBooleanCanonicalizingDeserializeProvider::class,
            )
                ->setDecoratedService(static::SERVICE_ID_DESERIALIZE_STATE_PROVIDER)
                ->setArguments([
                    new Reference(static::REFERENCE_INNER),
                    new Reference(ValidationConstraintReader::class),
                ]);
        }
    }
}
