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
}
