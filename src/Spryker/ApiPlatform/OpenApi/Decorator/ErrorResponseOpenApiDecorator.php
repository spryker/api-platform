<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ApiPlatformErrorSchemaRemover;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\DefaultErrorResponseAdder;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseDocumenter;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\GlueApiErrorSchema;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\OperationMetadataResolver;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\PathItemOperationAccessor;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\RequestAttributesResolver;

/**
 * Documents every error response with the error document the runtime really sends.
 *
 * API Platform leaves a response declared in the resource YAML as the description-only `Response` the generator
 * emits, attaches its own `Error` / `ConstraintViolation` schemas only to the 400 and 422 of write operations,
 * skips its default 403 and 404 as soon as they are declared and knows no 401 at all — so declared error
 * responses had no schema, undeclared ones had a wrong one and none had an example.
 *
 * Per operation, {@see DefaultErrorResponseAdder} adds the responses no YAML has to declare and
 * {@see ErrorResponseDocumenter} attaches {@see GlueApiErrorSchema} with the examples and descriptions, the
 * validation example naming the attributes {@see RequestAttributesResolver} reads from the request schema. Finally
 * the schema itself is registered and {@see ApiPlatformErrorSchemaRemover} drops API Platform's own error schemas
 * once nothing references them.
 *
 * Wrapped outermost around the OpenAPI factory (lower decoration priority than {@see OpenApiDecorator}), so it
 * works on the finished document: the documentation payload stays out of the operation metadata every request
 * loads, and the format transformers have already run. Each documented operation is joined to its metadata by
 * `operationId` through {@see OperationMetadataResolver}.
 */
class ErrorResponseOpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        protected readonly OpenApiFactoryInterface $decorated,
        protected readonly OperationMetadataResolver $operationMetadataResolver,
        protected readonly PathItemOperationAccessor $pathItemOperationAccessor,
        protected readonly DefaultErrorResponseAdder $defaultErrorResponseAdder,
        protected readonly RequestAttributesResolver $requestAttributesResolver,
        protected readonly ErrorResponseDocumenter $errorResponseDocumenter,
        protected readonly ApiPlatformErrorSchemaRemover $apiPlatformErrorSchemaRemover,
        protected readonly GlueApiErrorSchema $glueApiErrorSchema,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $schemas = $openApi->getComponents()->getSchemas() ?? new ArrayObject();

        $openApi = $this->documentErrorResponses($openApi, $schemas);

        $schemas[GlueApiErrorSchema::SCHEMA_NAME] = $this->glueApiErrorSchema->build();
        $this->apiPlatformErrorSchemaRemover->remove($openApi, $schemas);

        return $openApi->withComponents($openApi->getComponents()->withSchemas($schemas));
    }

    /**
     * @param \ArrayObject<string, mixed> $schemas
     */
    protected function documentErrorResponses(OpenApi $openApi, ArrayObject $schemas): OpenApi
    {
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $path => $pathItem) {
            foreach ($this->pathItemOperationAccessor->getOperations($pathItem) as $method => $operation) {
                $pathItem = $this->pathItemOperationAccessor->withOperation(
                    $pathItem,
                    $method,
                    $this->documentOperation($operation, $schemas),
                );
            }

            $paths->addPath((string)$path, $pathItem);
        }

        return $openApi;
    }

    /**
     * @param \ArrayObject<string, mixed> $schemas
     */
    protected function documentOperation(Operation $operation, ArrayObject $schemas): Operation
    {
        $httpOperation = $this->operationMetadataResolver->resolve($operation->getOperationId());

        if ($httpOperation !== null) {
            $operation = $this->defaultErrorResponseAdder->add($operation, $httpOperation);
        }

        return $this->errorResponseDocumenter->document(
            $operation,
            $httpOperation,
            $this->requestAttributesResolver->resolve($operation, $schemas),
        );
    }
}
