<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Exception\AccessDeniedException;
use ApiPlatform\Metadata\Exception\HttpExceptionInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use LogicException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface as SymfonyHttpExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Runs the resource operation that an MCP tool was generated from. The tool arguments become the
 * query parameters and URI variables of a copy of the current request, so the provider of the
 * operation reads them as on the REST call, and its response attributes do not reach the MCP response.
 * A renamed argument (`pageLimit`) goes back under its query parameter name (`page[limit]`).
 *
 * @implements \ApiPlatform\State\ProcessorInterface<object|null, \Mcp\Schema\Result\CallToolResult>
 */
class McpOperationToolProcessor implements ProcessorInterface
{
    public const string EXTRA_PROPERTY_SOURCE_OPERATION = 'mcpSourceOperation';

    public const string SOURCE_OPERATION_TYPE = 'type';

    public const string SOURCE_OPERATION_URI_TEMPLATE = 'uriTemplate';

    public const string SOURCE_OPERATION_QUERY_PARAMETER_NAMES = 'queryParameterNames';

    protected const string OPERATION_NAMESPACE = 'ApiPlatform\\Metadata';

    protected const string URI_TEMPLATE_FORMAT_SUFFIX = '{._format}';

    protected const string FORMAT_JSON = 'json';

    protected const string STRUCTURED_CONTENT_ITEMS = 'items';

    protected const string CONTEXT_REQUEST = 'request';

    protected const string SERVER_QUERY_STRING = 'QUERY_STRING';

    protected const string SERVER_REQUEST_METHOD = 'REQUEST_METHOD';

    protected const string QUERY_VALUE_TRUE = 'true';

    protected const string QUERY_VALUE_FALSE = 'false';

    protected const string QUERY_STRING_SEPARATOR = '&';

    protected const string QUERY_PARAMETER_LIST_SUFFIX_PATTERN = '/\[\]$/';

    protected const string MESSAGE_NOT_FOUND = 'Not Found';

    /**
     * Request attributes of the MCP request that describe the MCP call itself, not the source operation.
     *
     * @var array<string>
     */
    protected const array MCP_REQUEST_ATTRIBUTES = [
        '_api_query_parameters',
        '_api_filters',
        '_api_normalization_context',
        'data',
        'read_data',
        'previous_data',
        'mapped_data',
    ];

    /**
     * @param \ApiPlatform\State\ProviderInterface<object> $sourceOperationProvider
     */
    public function __construct(
        protected ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        protected ProviderInterface $sourceOperationProvider,
        protected NormalizerInterface $normalizer,
        protected RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws \ApiPlatform\Metadata\Exception\AccessDeniedException
     *
     * @return \Mcp\Schema\Result\CallToolResult|null
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $sourceOperation = $this->getSourceOperation($operation);
        $arguments = $this->extractArguments($data);
        $sourceUriVariables = array_intersect_key($arguments, $sourceOperation->getUriVariables() ?? []);
        $request = $this->createOperationRequest(
            $context[static::CONTEXT_REQUEST] ?? $this->requestStack->getCurrentRequest() ?? new Request(),
            $sourceOperation,
            $this->buildQuery(array_diff_key($arguments, $sourceUriVariables), $this->getQueryParameterNames($operation)),
            $sourceUriVariables,
        );

        $this->requestStack->push($request);

        try {
            $result = $this->sourceOperationProvider->provide($sourceOperation, $sourceUriVariables, [
                static::CONTEXT_REQUEST => $request,
                'operation' => $sourceOperation,
                'resource_class' => $sourceOperation->getClass(),
                'uri_variables' => $sourceUriVariables,
            ]);
        } catch (AccessDeniedException $exception) {
            throw $exception;
        } catch (HttpExceptionInterface | SymfonyHttpExceptionInterface $exception) {
            return $this->createResult(['statusCode' => $exception->getStatusCode(), 'message' => $exception->getMessage()], true);
        } finally {
            $this->requestStack->pop();
        }

        if ($result === null && !$sourceOperation instanceof CollectionOperationInterface) {
            return $this->createResult(['statusCode' => Response::HTTP_NOT_FOUND, 'message' => static::MESSAGE_NOT_FOUND], true);
        }

        return $this->createResult(
            $this->normalizeResult($result, $sourceOperation),
            $this->isErrorStatusCode($request->attributes->get(RequestAttribute::RESPONSE_STATUS_CODE)),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function getQueryParameterNames(Operation $operation): array
    {
        $queryParameterNames = $operation->getExtraProperties()[static::EXTRA_PROPERTY_SOURCE_OPERATION][static::SOURCE_OPERATION_QUERY_PARAMETER_NAMES] ?? [];

        return is_array($queryParameterNames) ? $queryParameterNames : [];
    }

    protected function getSourceOperation(Operation $operation): HttpOperation
    {
        $sourceOperation = $operation->getExtraProperties()[static::EXTRA_PROPERTY_SOURCE_OPERATION] ?? [];
        $operationClass = sprintf('%s\\%s', static::OPERATION_NAMESPACE, $sourceOperation[static::SOURCE_OPERATION_TYPE] ?? '');
        $uriTemplate = $sourceOperation[static::SOURCE_OPERATION_URI_TEMPLATE] ?? null;

        foreach ($this->resourceMetadataCollectionFactory->create((string)$operation->getClass()) as $resourceMetadata) {
            foreach ($resourceMetadata->getOperations() ?? [] as $candidateOperation) {
                if ($candidateOperation::class === $operationClass && ($uriTemplate === null || $this->normalizeUriTemplate($candidateOperation->getUriTemplate()) === $uriTemplate)) {
                    return $candidateOperation;
                }
            }
        }

        throw new LogicException(sprintf(
            'The source operation of the MCP tool "%s" is not found on the resource "%s". Run the resource generation again.',
            $operation->getName(),
            $operation->getClass(),
        ));
    }

    protected function normalizeUriTemplate(?string $uriTemplate): string
    {
        return str_replace(static::URI_TEMPLATE_FORMAT_SUFFIX, '', (string)$uriTemplate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractArguments(mixed $data): array
    {
        if (!is_object($data)) {
            return [];
        }

        return array_filter(get_object_vars($data), static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<string, mixed> $queryArguments
     * @param array<string, string> $queryParameterNames
     *
     * @return array<mixed>
     */
    protected function buildQuery(array $queryArguments, array $queryParameterNames): array
    {
        $queryStringParts = [];

        foreach ($queryArguments as $argumentName => $value) {
            $queryParameterName = $queryParameterNames[$argumentName] ?? (string)$argumentName;

            if (is_array($value)) {
                $queryParameterName = (string)preg_replace(static::QUERY_PARAMETER_LIST_SUFFIX_PATTERN, '', $queryParameterName);
            }

            $queryStringParts[] = http_build_query([$queryParameterName => $this->formatQueryValue($value)]);
        }

        parse_str(implode(static::QUERY_STRING_SEPARATOR, $queryStringParts), $query);

        return $query;
    }

    /**
     * @param array<mixed> $query
     * @param array<string, mixed> $uriVariables
     */
    protected function createOperationRequest(Request $mcpRequest, HttpOperation $sourceOperation, array $query, array $uriVariables): Request
    {
        $attributes = array_diff_key($mcpRequest->attributes->all(), array_flip(static::MCP_REQUEST_ATTRIBUTES));
        $server = array_merge($mcpRequest->server->all(), [
            static::SERVER_QUERY_STRING => http_build_query($query),
            static::SERVER_REQUEST_METHOD => $sourceOperation->getMethod(),
        ]);

        $request = $mcpRequest->duplicate($query, [], $attributes, null, null, $server);
        $request->attributes->add($uriVariables);
        $request->attributes->set(RequestAttribute::API_OPERATION, $sourceOperation);
        $request->attributes->set(RequestAttribute::API_OPERATION_NAME, $sourceOperation->getName());
        $request->attributes->set(RequestAttribute::API_RESOURCE_CLASS, $sourceOperation->getClass());
        $request->attributes->set(RequestAttribute::API_URI_VARIABLES, $uriVariables);
        $request->attributes->remove(RequestAttribute::RESPONSE_STATUS_CODE);

        return $request;
    }

    protected function formatQueryValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->formatQueryValue(...), $value);
        }

        if (is_bool($value)) {
            return $value ? static::QUERY_VALUE_TRUE : static::QUERY_VALUE_FALSE;
        }

        return is_scalar($value) ? (string)$value : $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeResult(mixed $result, HttpOperation $sourceOperation): array
    {
        $normalizationContext = array_merge($sourceOperation->getNormalizationContext() ?? [], [
            'operation' => $sourceOperation,
            'resource_class' => $sourceOperation->getClass(),
        ]);

        $normalizedResult = $this->normalizer->normalize($result, static::FORMAT_JSON, $normalizationContext);

        if ($sourceOperation instanceof CollectionOperationInterface) {
            return [static::STRUCTURED_CONTENT_ITEMS => is_array($normalizedResult) ? array_values($normalizedResult) : []];
        }

        return is_array($normalizedResult) ? $normalizedResult : [];
    }

    protected function isErrorStatusCode(mixed $statusCode): bool
    {
        return is_int($statusCode) && $statusCode >= Response::HTTP_BAD_REQUEST;
    }

    /**
     * @param array<string, mixed> $structuredContent
     */
    protected function createResult(array $structuredContent, bool $isError): CallToolResult
    {
        return new CallToolResult(
            [new TextContent(json_encode($structuredContent, JSON_THROW_ON_ERROR))],
            $isError,
            $structuredContent,
        );
    }
}
