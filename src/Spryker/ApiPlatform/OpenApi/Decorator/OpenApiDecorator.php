<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;

/**
 * Unified OpenAPI decorator that applies format-specific transformations using pluggable formatters.
 *
 * This decorator:
 * 1. Transforms schemas using registered format transformers (adds examples, creates variants)
 * 2. Fixes request body schema references based on HTTP method and content type
 *
 * New format transformers can be added by implementing FormatTransformerInterface
 * and tagging them with 'spryker_api_platform.format_transformer'.
 */
class OpenApiDecorator implements OpenApiFactoryInterface
{
    protected const array METHODS_WITH_REQUEST_BODY = ['post', 'put', 'patch'];

    protected const array HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    protected const string ACCEPT_LANGUAGE_HEADER_NAME = 'Accept-Language';

    protected const string ACCEPT_LANGUAGE_HEADER_IN = 'header';

    protected const string ACCEPT_LANGUAGE_HEADER_DESCRIPTION = 'Preferred language for the response (e.g., `de`, `en-US`). Uses standard HTTP content negotiation.';

    protected const string ACCEPT_LANGUAGE_HEADER_EXAMPLE = 'de';

    protected const string ACCEPT_LANGUAGE_SCHEMA_TYPE = 'string';

    protected const string SPARSE_FIELDSETS_PARAMETER_NAME = 'fields[]';

    protected const string SPARSE_FIELDSETS_PARAMETER_IN = 'query';

    protected const string SPARSE_FIELDSETS_PARAMETER_DESCRIPTION = 'Allows you to reduce the response to contain only the properties you need. Use sparse fieldsets to select exactly which properties should be returned.';

    protected const string SPARSE_FIELDSETS_SCHEMA_TYPE = 'array';

    protected const string SPARSE_FIELDSETS_ITEMS_TYPE = 'string';

    /**
     * @var array<string, \Spryker\ApiPlatform\OpenApi\FormatTransformer\FormatTransformerInterface>
     */
    protected array $transformersByMimeType = [];

    /**
     * @param iterable<\Spryker\ApiPlatform\OpenApi\FormatTransformer\FormatTransformerInterface> $formatTransformers
     */
    public function __construct(
        protected readonly OpenApiFactoryInterface $decorated,
        iterable $formatTransformers = [],
    ) {
        foreach ($formatTransformers as $transformer) {
            foreach ($transformer->getMimeTypes() as $mimeType) {
                $this->transformersByMimeType[$mimeType] = $transformer;
            }
        }
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $openApi = $this->transformSchemas($openApi);
        $openApi = $this->fixRequestBodyReferences($openApi);
        $openApi = $this->addAcceptLanguageHeader($openApi);
        $openApi = $this->addSparseFieldsetsParameter($openApi);

        return $openApi;
    }

    /**
     * Applies schema transformations using all registered format transformers.
     */
    protected function transformSchemas(OpenApi $openApi): OpenApi
    {
        $components = $openApi->getComponents();
        $schemas = $components->getSchemas();

        if ($schemas === null) {
            return $openApi;
        }

        foreach ($this->transformersByMimeType as $transformer) {
            $schemas = $transformer->transformSchemas($schemas);
        }

        return $openApi->withComponents(
            new Components(
                schemas: $schemas,
                responses: $components->getResponses(),
                parameters: $components->getParameters(),
                examples: $components->getExamples(),
                requestBodies: $components->getRequestBodies(),
                headers: $components->getHeaders(),
                securitySchemes: $components->getSecuritySchemes(),
                links: $components->getLinks(),
                callbacks: $components->getCallbacks(),
            ),
        );
    }

    /**
     * Fixes request body schema references for all paths and methods.
     */
    protected function fixRequestBodyReferences(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $path => $pathItem) {
            foreach (static::METHODS_WITH_REQUEST_BODY as $method) {
                $getter = sprintf('get%s', ucfirst($method));
                $setter = sprintf('with%s', ucfirst($method));

                $operation = $pathItem->$getter();

                if ($operation === null || $operation->getRequestBody() === null) {
                    continue;
                }

                $requestBody = $operation->getRequestBody();
                $content = $requestBody->getContent();

                if ($content === null) {
                    continue;
                }

                $newContent = $this->fixContentSchemaReferences($content, $method);

                if ($newContent !== $content) {
                    $pathItem = $pathItem->$setter(
                        $operation->withRequestBody(
                            new RequestBody(
                                description: $requestBody->getDescription(),
                                content: $newContent,
                                required: $requestBody->getRequired(),
                            ),
                        ),
                    );

                    $paths->addPath($path, $pathItem);
                }
            }
        }

        return $openApi;
    }

    /**
     * Fixes schema references in request body content using appropriate format transformer.
     *
     * @param \ArrayObject<string, \ApiPlatform\OpenApi\Model\MediaType> $content
     *
     * @return \ArrayObject<string, \ApiPlatform\OpenApi\Model\MediaType>
     */
    protected function fixContentSchemaReferences(ArrayObject $content, string $method): ArrayObject
    {
        /** @var \ArrayObject<string, \ApiPlatform\OpenApi\Model\MediaType> $newContent */
        $newContent = new ArrayObject();

        foreach ($content as $mimeType => $mediaType) {
            /** @phpstan-ignore instanceof.alwaysTrue */
            if (!$mediaType instanceof MediaType) {
                $newContent[$mimeType] = $mediaType;

                continue;
            }

            $schema = $mediaType->getSchema();

            if ($schema === null || !isset($schema['$ref'])) {
                $newContent[$mimeType] = $mediaType;

                continue;
            }

            $transformer = $this->transformersByMimeType[$mimeType] ?? null;

            if ($transformer === null) {
                $newContent[$mimeType] = $mediaType;

                continue;
            }

            $ref = $schema['$ref'];
            $newRef = $transformer->fixRequestBodyReference($ref, $method);

            $newContent[$mimeType] = $mediaType;

            if ($newRef !== $ref) {
                $schema['$ref'] = $newRef;
                $newContent[$mimeType] = $mediaType->withSchema($schema);
            }
        }

        return $newContent;
    }

    /**
     * Adds Accept-Language header parameter to all operations in the OpenAPI spec.
     */
    protected function addAcceptLanguageHeader(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();
        $acceptLanguageParameter = $this->createAcceptLanguageParameter();

        foreach ($paths->getPaths() as $path => $pathItem) {
            foreach (static::HTTP_METHODS as $method) {
                $getter = sprintf('get%s', ucfirst($method));
                $setter = sprintf('with%s', ucfirst($method));

                $operation = $pathItem->$getter();

                if ($operation === null) {
                    continue;
                }

                $parameters = $operation->getParameters();
                $parameters[] = $acceptLanguageParameter;

                $pathItem = $pathItem->$setter(
                    $operation->withParameters($parameters),
                );
            }

            $paths->addPath($path, $pathItem);
        }

        return $openApi;
    }

    protected function createAcceptLanguageParameter(): Parameter
    {
        return new Parameter(
            name: static::ACCEPT_LANGUAGE_HEADER_NAME,
            in: static::ACCEPT_LANGUAGE_HEADER_IN,
            description: static::ACCEPT_LANGUAGE_HEADER_DESCRIPTION,
            required: false,
            schema: ['type' => static::ACCEPT_LANGUAGE_SCHEMA_TYPE],
            example: static::ACCEPT_LANGUAGE_HEADER_EXAMPLE,
        );
    }

    /**
     * Adds sparse fieldsets (fields[]) query parameter to all GET operations that don't already have it.
     */
    protected function addSparseFieldsetsParameter(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();
        $sparseFieldsetsParameter = $this->createSparseFieldsetsParameter();

        foreach ($paths->getPaths() as $path => $pathItem) {
            $operation = $pathItem->getGet();

            if ($operation === null) {
                continue;
            }

            if ($this->hasSparseFieldsetsParameter($operation->getParameters())) {
                continue;
            }

            $parameters = $operation->getParameters();
            $parameters[] = $sparseFieldsetsParameter;

            $pathItem = $pathItem->withGet(
                $operation->withParameters($parameters),
            );

            $paths->addPath($path, $pathItem);
        }

        return $openApi;
    }

    /**
     * @param array<\ApiPlatform\OpenApi\Model\Parameter> $parameters
     */
    protected function hasSparseFieldsetsParameter(array $parameters): bool
    {
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === static::SPARSE_FIELDSETS_PARAMETER_NAME) {
                return true;
            }
        }

        return false;
    }

    protected function createSparseFieldsetsParameter(): Parameter
    {
        return new Parameter(
            name: static::SPARSE_FIELDSETS_PARAMETER_NAME,
            in: static::SPARSE_FIELDSETS_PARAMETER_IN,
            description: static::SPARSE_FIELDSETS_PARAMETER_DESCRIPTION,
            required: false,
            schema: [
                'type' => static::SPARSE_FIELDSETS_SCHEMA_TYPE,
                'items' => ['type' => static::SPARSE_FIELDSETS_ITEMS_TYPE],
            ],
        );
    }
}
