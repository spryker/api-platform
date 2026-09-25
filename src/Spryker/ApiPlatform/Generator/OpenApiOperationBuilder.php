<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Generator\MediaType\MediaTypeFormatterRegistry;

/**
 * Generates the `openapi: new Operation(...)` argument of a generated operation attribute from the
 * operation's `openapiContext` in the resource YAML: `summary`, `parameters`, `responses` and a
 * `requestBody`. Query parameters declared there are what Swagger UI renders as editable inputs in
 * "Try it out"; anything only mentioned in the operation description is not.
 *
 * Input (`openapiContext` of one operation):
 * ```yaml
 * summary: 'List glossary keys'
 * parameters:
 *     - name: 'sort'
 *       in: query
 *       description: 'Sort field.'
 *       schema: { type: string, enum: ['key', '-key'] }
 *       example: '-key'
 * responses:
 *     200: { description: 'Glossary keys returned.' }
 * ```
 *
 * Output:
 * ```php
 * new Operation(
 *     tags: ['glossary-keys'],
 *     summary: 'List glossary keys',
 *     parameters: [
 *         new Parameter(name: 'sort', in: 'query', description: 'Sort field.', schema: ['type' => 'string', 'enum' => ['key', '-key']], example: '-key'),
 *     ],
 *     responses: [
 *         200 => new Response(description: 'Glossary keys returned.'),
 *     ],
 * )
 * ```
 *
 * A request body example is generated for write operations (Post, Patch, Put) from the resource
 * properties through the media type formatters, unless the YAML provides `requestBody` explicitly.
 */
class OpenApiOperationBuilder
{
    protected const array WRITE_OPERATION_TYPES = ['Post', 'Patch', 'Put'];

    protected const string CONTEXT_KEY_SUMMARY = 'summary';

    protected const string CONTEXT_KEY_PARAMETERS = 'parameters';

    protected const string CONTEXT_KEY_RESPONSES = 'responses';

    protected const string CONTEXT_KEY_REQUEST_BODY = 'requestBody';

    protected const string CONTEXT_KEY_CONTENT = 'content';

    protected const string CONTEXT_KEY_DESCRIPTION = 'description';

    protected const string PARAMETER_KEY_NAME = 'name';

    protected const string PARAMETER_KEY_IN = 'in';

    protected const string PARAMETER_DEFAULT_IN = 'query';

    protected const string PARAMETER_KEY_EXAMPLE = 'example';

    protected const string PARAMETER_KEY_EXAMPLES = 'examples';

    protected const string EXAMPLE_KEY_SUMMARY = 'summary';

    protected const string EXAMPLE_KEY_DESCRIPTION = 'description';

    protected const string EXAMPLE_KEY_VALUE = 'value';

    /**
     * @var array<string>
     */
    protected const array PARAMETER_PASSTHROUGH_KEYS = ['description', 'required', 'deprecated', 'schema', 'example', 'style', 'explode'];

    /**
     * @var array<string>
     */
    protected const array EXAMPLE_PASSTHROUGH_KEYS = [self::EXAMPLE_KEY_SUMMARY, self::EXAMPLE_KEY_DESCRIPTION];

    /**
     * @param array<string, array<string>> $apiPlatformFormats
     */
    public function __construct(
        protected readonly MediaTypeFormatterRegistry $formatterRegistry,
        protected readonly array $apiPlatformFormats,
    ) {
    }

    /**
     * @param array<string, mixed> $parsedSchema
     * @param array<string, mixed> $operation
     * @param array<string>|null $tags
     */
    public function generateOpenApiOperation(
        array $parsedSchema,
        array $operation,
        string $operationType,
        ?array $tags = null,
        int $indentLevel = 3,
    ): string {
        $openapiContext = is_array($operation['openapiContext'] ?? null) ? $operation['openapiContext'] : [];
        $operationParts = [];

        if ($tags !== null && $tags !== []) {
            $operationParts[] = $this->formatTagsParameter($tags);
        }

        if (isset($openapiContext[static::CONTEXT_KEY_SUMMARY]) && is_string($openapiContext[static::CONTEXT_KEY_SUMMARY])) {
            $operationParts[] = sprintf("summary: '%s'", addslashes($openapiContext[static::CONTEXT_KEY_SUMMARY]));
        }

        $parameters = $this->formatParameters(
            $openapiContext[static::CONTEXT_KEY_PARAMETERS] ?? null,
            $indentLevel,
            $this->describeOperation($parsedSchema, $operation, $operationType),
        );

        if ($parameters !== '') {
            $operationParts[] = $parameters;
        }

        $responses = $this->formatResponses($openapiContext[static::CONTEXT_KEY_RESPONSES] ?? null, $indentLevel);

        if ($responses !== '') {
            $operationParts[] = $responses;
        }

        $requestBody = $this->buildRequestBodyPart($parsedSchema, $operationType, $openapiContext);

        if ($requestBody !== '') {
            $operationParts[] = $requestBody;
        }

        return $this->buildOperationString($operationParts, $indentLevel);
    }

    /**
     * A query parameter is contract exactly as a response status is: a `filter[...]` the schema does
     * not declare is a capability no client can discover and no gate can enforce. A parameter that
     * cannot be emitted would leave the schema silently, so it fails generation instead.
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     */
    protected function formatParameters(mixed $parameters, int $indentLevel, string $operationLocator = ''): string
    {
        if (!is_array($parameters)) {
            return '';
        }

        $parameterParts = [];

        foreach ($parameters as $index => $parameter) {
            if (!is_array($parameter) || !is_string($parameter[static::PARAMETER_KEY_NAME] ?? null)) {
                throw new ApiSchemaGenerationException(sprintf(
                    'openapiContext.parameters entry #%d%s declares no "name". It is required by OpenAPI, '
                        . 'and a parameter that cannot be emitted would leave the schema silently - '
                        . 'declare it in the resource yml.',
                    (int)$index,
                    $operationLocator === '' ? '' : sprintf(' of %s', $operationLocator),
                ));
            }

            $parameterParts[] = $this->formatParameter($parameter);
        }

        return $this->formatNamedList(static::CONTEXT_KEY_PARAMETERS, $parameterParts, $indentLevel);
    }

    /**
     * Names the operation a generation failure came from, so the message points at the declaration
     * to change rather than at the code that threw.
     *
     * @param array<string, mixed> $parsedSchema
     * @param array<string, mixed> $operation
     */
    protected function describeOperation(array $parsedSchema, array $operation, string $operationType): string
    {
        $parts = [];

        if (is_string($parsedSchema[SchemaKey::SHORT_NAME] ?? null)) {
            $parts[] = $parsedSchema[SchemaKey::SHORT_NAME];
        }

        $parts[] = $operationType;

        if (is_string($operation[SchemaKey::URI_TEMPLATE] ?? null)) {
            $parts[] = $operation[SchemaKey::URI_TEMPLATE];
        }

        $locator = implode(' ', $parts);

        if (is_string($parsedSchema[SchemaKey::SOURCE_FILE] ?? null)) {
            $locator .= sprintf(' (%s)', $parsedSchema[SchemaKey::SOURCE_FILE]);
        }

        return $locator;
    }

    /**
     * @param array<string, mixed> $parameter
     */
    protected function formatParameter(array $parameter): string
    {
        $in = is_string($parameter[static::PARAMETER_KEY_IN] ?? null) ? $parameter[static::PARAMETER_KEY_IN] : static::PARAMETER_DEFAULT_IN;

        $arguments = [
            sprintf("name: '%s'", addslashes($parameter[static::PARAMETER_KEY_NAME])),
            sprintf("in: '%s'", addslashes($in)),
        ];

        $examples = $this->formatExamples($parameter[static::PARAMETER_KEY_EXAMPLES] ?? null);

        foreach (static::PARAMETER_PASSTHROUGH_KEYS as $key) {
            if (!array_key_exists($key, $parameter) || ($examples !== '' && $key === static::PARAMETER_KEY_EXAMPLE)) {
                continue;
            }

            $arguments[] = sprintf('%s: %s', $key, $this->formatOpenapiContextValue($parameter[$key]));
        }

        if ($examples !== '') {
            $arguments[] = sprintf('%s: %s', static::PARAMETER_KEY_EXAMPLES, $examples);
        }

        return sprintf('new Parameter(%s)', implode(', ', $arguments));
    }

    protected function formatExamples(mixed $examples): string
    {
        if (!is_array($examples) || $examples === []) {
            return '';
        }

        $exampleParts = [];

        foreach ($examples as $exampleKey => $example) {
            if (!is_array($example)) {
                continue;
            }

            $exampleParts[] = sprintf("'%s' => %s", addslashes((string)$exampleKey), $this->formatExample($example));
        }

        if ($exampleParts === []) {
            return '';
        }

        return sprintf('new ArrayObject([%s])', implode(', ', $exampleParts));
    }

    /**
     * @param array<string, mixed> $example
     */
    protected function formatExample(array $example): string
    {
        $arguments = [];

        foreach (static::EXAMPLE_PASSTHROUGH_KEYS as $key) {
            if (!is_string($example[$key] ?? null)) {
                continue;
            }

            $arguments[] = sprintf("%s: '%s'", $key, addslashes($example[$key]));
        }

        if (array_key_exists(static::EXAMPLE_KEY_VALUE, $example)) {
            $arguments[] = sprintf('%s: %s', static::EXAMPLE_KEY_VALUE, $this->formatOpenapiContextValue($example[static::EXAMPLE_KEY_VALUE]));
        }

        return sprintf('new Example(%s)', implode(', ', $arguments));
    }

    /**
     * The schema's declared responses are the API contract: they drive both the OpenAPI document and
     * the contract coverage gate, so every declared status is carried through verbatim.
     */
    protected function formatResponses(mixed $responses, int $indentLevel): string
    {
        if (!is_array($responses)) {
            return '';
        }

        $responseParts = [];

        foreach ($responses as $status => $response) {
            $description = is_array($response) ? ($response[static::CONTEXT_KEY_DESCRIPTION] ?? null) : $response;

            if (!is_string($description)) {
                continue;
            }

            $statusKey = is_int($status) ? (string)$status : sprintf("'%s'", addslashes((string)$status));
            $responseParts[] = sprintf("%s => new Response(description: '%s')", $statusKey, $this->escapeSingleQuoted($description));
        }

        return $this->formatNamedList(static::CONTEXT_KEY_RESPONSES, $responseParts, $indentLevel);
    }

    /**
     * @param array<string> $items
     */
    protected function formatNamedList(string $name, array $items, int $indentLevel): string
    {
        if ($items === []) {
            return '';
        }

        $itemIndent = $this->indent($indentLevel + 2);
        $closeIndent = $this->indent($indentLevel + 1);

        return sprintf("%s: [\n%s%s,\n%s]", $name, $itemIndent, implode(",\n" . $itemIndent, $items), $closeIndent);
    }

    /**
     * @param array<string, mixed> $parsedSchema
     * @param array<string, mixed> $openapiContext
     */
    protected function buildRequestBodyPart(array $parsedSchema, string $operationType, array $openapiContext): string
    {
        $explicitContent = $openapiContext[static::CONTEXT_KEY_REQUEST_BODY][static::CONTEXT_KEY_CONTENT] ?? null;

        if (is_array($explicitContent)) {
            return sprintf(
                'requestBody: new RequestBody(content: new ArrayObject(%s), required: true)',
                $this->formatOpenapiContextContent($explicitContent),
            );
        }

        if (!in_array($operationType, static::WRITE_OPERATION_TYPES, true)) {
            return '';
        }

        $formatters = $this->formatterRegistry->getFormattersForMediaTypes($this->getEnabledMimeTypes());
        $contentParts = [];

        foreach ($formatters as $mediaType => $formatter) {
            $example = $formatter->buildExample($parsedSchema, $operationType);

            if (empty($example['data']['attributes'])) {
                continue;
            }

            $contentParts[] = sprintf("'%s' => new MediaType(example: %s)", $mediaType, $formatter->formatExampleAsCode($example));
        }

        if ($contentParts === []) {
            return '';
        }

        return sprintf('requestBody: new RequestBody(content: new ArrayObject([%s]), required: true)', implode(', ', $contentParts));
    }

    /**
     * @param array<string> $parts
     */
    protected function buildOperationString(array $parts, int $indentLevel): string
    {
        if ($parts === []) {
            return '';
        }

        $paramIndent = $this->indent($indentLevel + 1);
        $closeIndent = $this->indent($indentLevel);
        $content = $paramIndent . implode(",\n" . $paramIndent, $parts);

        return sprintf("new Operation(\n%s,\n%s)", $content, $closeIndent);
    }

    /**
     * Only the backslash and the single quote carry meaning inside a single-quoted PHP literal.
     * `addslashes()` would also escape the double quote, emitting a stray backslash into the
     * generated description.
     */
    protected function escapeSingleQuoted(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    /**
     * @param array<string> $tags
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
     * @return array<string>
     */
    protected function getEnabledMimeTypes(): array
    {
        $mimeTypes = [];

        foreach ($this->apiPlatformFormats as $formatMimeTypes) {
            if (isset($formatMimeTypes[0])) {
                $mimeTypes[] = $formatMimeTypes[0];
            }
        }

        return $mimeTypes;
    }

    /**
     * @param array<string, mixed> $content
     */
    protected function formatOpenapiContextContent(array $content): string
    {
        $parts = [];

        foreach ($content as $mediaType => $mediaTypeData) {
            $parts[] = sprintf("'%s' => %s", $mediaType, $this->formatOpenapiContextArray((array)$mediaTypeData));
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
     */
    protected function formatOpenapiContextArray(array $array): string
    {
        if ($array === []) {
            return '[]';
        }

        if (array_is_list($array)) {
            $items = array_map(fn (mixed $item): string => $this->formatOpenapiContextValue($item), $array);

            return '[' . implode(', ', $items) . ']';
        }

        $parts = [];

        foreach ($array as $key => $value) {
            $parts[] = sprintf("'%s' => %s", $key, $this->formatOpenapiContextValue($value));
        }

        return '[' . implode(', ', $parts) . ']';
    }

    protected function indent(int $level): string
    {
        return str_repeat('    ', $level);
    }
}
