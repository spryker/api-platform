<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema;

use Spryker\ApiPlatform\Generator\SchemaKey;

class McpToolSchemaResolver
{
    protected const string PARAMETER_LOCATION_QUERY = 'query';

    protected const string ARGUMENT_NAME_WORD_SEPARATOR_PATTERN = '/[^A-Za-z0-9]+/';

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, \Spryker\ApiPlatform\Schema\McpToolDefinition>
     */
    public function resolveTools(array $schema): array
    {
        $tools = [];
        $operations = $schema[SchemaKey::OPERATIONS] ?? [];

        if (!is_array($operations)) {
            return $tools;
        }

        foreach ($operations as $operationKey => $operation) {
            if (!is_array($operation)) {
                continue;
            }

            $tool = $this->resolveToolDefinition($operation[SchemaKey::MCP] ?? null);

            if ($tool === null) {
                continue;
            }

            $toolName = isset($tool[McpSchemaKeys::NAME]) && is_string($tool[McpSchemaKeys::NAME])
                ? $tool[McpSchemaKeys::NAME]
                : $this->buildDefaultToolName($schema, (string)$operationKey);

            $tools[$toolName] = new McpToolDefinition($toolName, (string)$operationKey, $operation, $tool);
        }

        return $tools;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveToolDefinition(mixed $mcp): ?array
    {
        if ($mcp === true) {
            return [];
        }

        return is_array($mcp) ? $mcp : null;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, array<string, mixed>> Keys are the tool argument names, values are the query parameter definitions.
     */
    public function resolveQueryParameters(array $operation): array
    {
        $queryParameters = [];

        foreach ($operation[SchemaKey::OPEN_API_CONTEXT]['parameters'] ?? [] as $parameter) {
            if (!is_array($parameter) || ($parameter['in'] ?? null) !== static::PARAMETER_LOCATION_QUERY || !isset($parameter['name'])) {
                continue;
            }

            $queryParameters[$this->buildArgumentName((string)$parameter['name'])] = $parameter;
        }

        return $queryParameters;
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function buildDefaultToolName(array $schema, string $operationKey): string
    {
        $resourceName = (string)($schema[SchemaKey::SHORT_NAME] ?? $schema[SchemaKey::NAME] ?? '');

        return sprintf('%s-%s', $resourceName, strtolower((string)preg_replace('/(?<!^)[A-Z]/', '-$0', $operationKey)));
    }

    protected function buildArgumentName(string $queryParameterName): string
    {
        $words = preg_split(static::ARGUMENT_NAME_WORD_SEPARATOR_PATTERN, $queryParameterName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return lcfirst(implode('', array_map('ucfirst', $words)));
    }
}
