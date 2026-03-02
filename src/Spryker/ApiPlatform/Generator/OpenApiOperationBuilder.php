<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Generator\MediaType\MediaTypeFormatterRegistry;

/**
 * Builds OpenAPI operation definitions with request body examples for write operations.
 *
 * Generates OpenAPI Operation objects with RequestBody and MediaType components for
 * Post, Patch, and Put operations, supporting multiple content types through media type formatters.
 *
 * The builder automatically generates examples for all configured and supported media types
 * by reading the API Platform formats configuration and using registered media type formatters.
 * Unsupported media types are gracefully skipped.
 *
 * Input schema excerpt for operation:
 * ```php
 * [
 *     'shortName' => 'customers',
 *     'properties' => [
 *         'email' => ['type' => 'string', 'openapiContext' => ['example' => 'test@example.com']],
 *         'firstName' => ['type' => 'string', 'openapiContext' => ['example' => 'John']],
 *     ],
 *     'operations' => [
 *         'Post' => [...],
 *     ],
 * ]
 * ```
 *
 * Generated output for Post operation with multiple formats:
 * ```php
 * new Post(openapi: new Operation(requestBody: new RequestBody(content: new ArrayObject([
 *     'application/vnd.api+json' => new MediaType(example: [
 *         'data' => [
 *             'type' => 'customers',
 *             'attributes' => [
 *                 'email' => 'test@example.com',
 *                 'firstName' => 'John',
 *             ],
 *         ],
 *     ]),
 *     'application/xml' => new MediaType(example: [...])
 * ])))
 * ```
 *
 * Automatically filters out identifier and read-only properties from write operation examples.
 * Supports custom OpenAPI context from operation definition for advanced request body customization.
 */
class OpenApiOperationBuilder
{
    /**
     * @param array<string, array<string>> $apiPlatformFormats Formats from api_platform.formats parameter
     */
    public function __construct(
        protected readonly MediaTypeFormatterRegistry $formatterRegistry,
        protected readonly array $apiPlatformFormats,
    ) {
    }

    /**
     * @param array<string, mixed> $parsedSchema
     * @param array<string, mixed> $operation
     * @param string $operationType
     * @param array<string>|null $tags
     *
     * @return string
     */
    public function generateOpenApiOperation(
        array $parsedSchema,
        array $operation,
        string $operationType,
        ?array $tags = null
    ): string {
        $operationParameters = [];

        if ($tags !== null && $tags !== []) {
            $operationParameters['tags'] = $this->formatTagsParameter($tags);
        }

        if (isset($operation['openapiContext']['requestBody'])) {
            $content = $this->formatOpenapiContextContent($operation['openapiContext']['requestBody']['content']);
            $operationParameters['requestBody'] = sprintf('new RequestBody(content: new ArrayObject(%s))', $content);

            return $this->buildOperationWithParameters($operationType, $operationParameters);
        }

        $enabledMimeTypes = $this->getEnabledMimeTypes();

        $formatters = $this->formatterRegistry->getFormattersForMediaTypes($enabledMimeTypes);

        if ($formatters === []) {
            if ($operationParameters !== []) {
                return $this->buildOperationWithParameters($operationType, $operationParameters);
            }

            return sprintf('new %s()', $operationType);
        }

        $contentParts = [];

        foreach ($formatters as $mediaType => $formatter) {
            $example = $formatter->buildExample($parsedSchema, $operationType);

            if (empty($example['data']['attributes'])) {
                continue;
            }

            $exampleString = $formatter->formatExampleAsCode($example);
            $contentParts[] = sprintf(
                "'%s' => new MediaType(example: %s)",
                $mediaType,
                $exampleString,
            );
        }

        if ($contentParts === []) {
            if ($operationParameters !== []) {
                return $this->buildOperationWithParameters($operationType, $operationParameters);
            }

            return sprintf('new %s()', $operationType);
        }

        $operationParameters['requestBody'] = sprintf(
            'new RequestBody(content: new ArrayObject([%s]))',
            implode(', ', $contentParts),
        );

        return $this->buildOperationWithParameters($operationType, $operationParameters);
    }

    /**
     * @param string $operationType
     * @param array<string, string> $parameters
     *
     * @return string
     */
    protected function buildOperationWithParameters(string $operationType, array $parameters): string
    {
        if ($parameters === []) {
            return sprintf('new %s()', $operationType);
        }

        $parameterString = implode(', ', $parameters);

        return sprintf(
            'new %s(openapi: new Operation(%s))',
            $operationType,
            $parameterString,
        );
    }

    /**
     * @param array<string> $tags
     *
     * @return string
     */
    protected function formatTagsParameter(array $tags): string
    {
        $formattedTags = array_map(
            fn (string $tag): string => sprintf("'%s'", str_replace("'", "\\'", $tag)),
            $tags,
        );

        return sprintf('tags: [%s]', implode(', ', $formattedTags));
    }

    /**
     * Extracts all mime types from API Platform formats configuration.
     *
     * @return array<string>
     */
    protected function getEnabledMimeTypes(): array
    {
        $mimeTypes = [];

        foreach ($this->apiPlatformFormats as $format => $formatMimeTypes) {
            if (isset($formatMimeTypes[0])) {
                $mimeTypes[] = $formatMimeTypes[0];
            }
        }

        return $mimeTypes;
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return string
     */
    protected function formatOpenapiContextContent(array $content): string
    {
        $parts = [];

        foreach ($content as $mediaType => $mediaTypeData) {
            $formattedMediaTypeData = $this->formatOpenapiContext($mediaTypeData);
            $parts[] = sprintf("'%s' => %s", $mediaType, $formattedMediaTypeData);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return string
     */
    protected function formatOpenapiContext(array $context): string
    {
        $parts = [];

        foreach ($context as $key => $value) {
            $formattedValue = $this->formatOpenapiContextValue($value);
            $parts[] = sprintf("'%s' => %s", $key, $formattedValue);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    protected function formatOpenapiContextValue(mixed $value): string
    {
        if (is_array($value)) {
            return $this->formatOpenapiContextArray($value);
        }

        if (is_string($value)) {
            return sprintf("'%s'", addslashes($value));
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string)$value;
    }

    /**
     * @param array<mixed> $array
     *
     * @return string
     */
    protected function formatOpenapiContextArray(array $array): string
    {
        if ($array === []) {
            return '[]';
        }

        $isAssociative = array_keys($array) !== range(0, count($array) - 1);

        if ($isAssociative) {
            $parts = [];

            foreach ($array as $key => $value) {
                $formattedValue = $this->formatOpenapiContextValue($value);
                $parts[] = sprintf("'%s' => %s", $key, $formattedValue);
            }

            return '[' . implode(', ', $parts) . ']';
        }

        $items = array_map(
            fn (mixed $item): string => $this->formatOpenapiContextValue($item),
            $array,
        );

        return '[' . implode(', ', $items) . ']';
    }
}
