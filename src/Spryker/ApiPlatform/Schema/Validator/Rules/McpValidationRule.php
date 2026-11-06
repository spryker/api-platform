<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

use Spryker\ApiPlatform\Generator\SchemaKey;
use Spryker\ApiPlatform\Schema\McpSchemaKeys;
use Spryker\ApiPlatform\Schema\McpToolSchemaResolver;

/**
 * Validates the `mcp` key of the resource operations: `true`, `false` or a map of tool settings.
 */
class McpValidationRule implements ValidationRuleInterface
{
    protected const string TOOL_NAME_PATTERN = '/^[A-Za-z0-9_.-]{1,128}$/';

    protected const string ANNOTATION_TITLE = 'title';

    /**
     * @var array<string>
     */
    protected const array ANNOTATION_HINT_KEYS = ['readOnlyHint', 'destructiveHint', 'idempotentHint', 'openWorldHint'];

    public function __construct(protected McpToolSchemaResolver $mcpToolSchemaResolver)
    {
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $operations = $schema[SchemaKey::OPERATIONS] ?? null;

        if (!is_array($operations)) {
            return [];
        }

        $sourceFile = $schema['sourceFile'] ?? 'unknown file';
        $errors = [];
        $operationKeysByToolName = [];

        foreach ($operations as $operationKey => $operation) {
            if (!is_array($operation) || !array_key_exists(SchemaKey::MCP, $operation)) {
                continue;
            }

            $operationErrors = $this->validateOperation((string)$operationKey, $operation, $sourceFile);
            $errors = array_merge($errors, $operationErrors);

            $tool = $this->mcpToolSchemaResolver->resolveToolDefinition($operation[SchemaKey::MCP]);

            if ($operationErrors !== [] || $tool === null) {
                continue;
            }

            $toolName = $tool[McpSchemaKeys::NAME] ?? $this->mcpToolSchemaResolver->buildDefaultToolName($schema, (string)$operationKey);
            $operationKeysByToolName[$toolName][] = (string)$operationKey;
        }

        foreach ($operationKeysByToolName as $toolName => $operationKeys) {
            if (count($operationKeys) > 1) {
                $errors[] = sprintf('MCP tool name "%s" is declared by more than one operation (%s) in %s', $toolName, implode(', ', $operationKeys), $sourceFile);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string>
     */
    protected function validateOperation(string $operationKey, array $operation, string $sourceFile): array
    {
        $mcp = $operation[SchemaKey::MCP];

        if ($mcp === false) {
            return [];
        }

        $operationType = (string)($operation[SchemaKey::TYPE] ?? $operationKey);

        if (!in_array($operationType, McpSchemaKeys::OPERATION_TYPES, true)) {
            return [sprintf('Operation "%s" cannot be an MCP tool in %s. Allowed operation types: %s', $operationKey, $sourceFile, implode(', ', McpSchemaKeys::OPERATION_TYPES))];
        }

        if ($mcp === true) {
            return [];
        }

        if (!is_array($mcp) || ($mcp !== [] && array_is_list($mcp))) {
            return [sprintf('Operation "%s" mcp must be true, false or a map in %s', $operationKey, $sourceFile)];
        }

        return $this->validateTool($operationKey, $mcp, $sourceFile);
    }

    /**
     * @param array<string, mixed> $tool
     *
     * @return array<string>
     */
    protected function validateTool(string $operationKey, array $tool, string $sourceFile): array
    {
        $errors = [];

        foreach (array_diff(array_keys($tool), McpSchemaKeys::TOOL_KEYS) as $unknownKey) {
            $errors[] = sprintf('Operation "%s" mcp has the unknown key "%s" in %s. Allowed keys: %s', $operationKey, $unknownKey, $sourceFile, implode(', ', McpSchemaKeys::TOOL_KEYS));
        }

        foreach (McpSchemaKeys::STRING_KEYS as $key) {
            if (isset($tool[$key]) && !is_string($tool[$key])) {
                $errors[] = sprintf('Operation "%s" mcp %s must be a string in %s', $operationKey, $key, $sourceFile);
            }
        }

        if (isset($tool[McpSchemaKeys::NAME]) && is_string($tool[McpSchemaKeys::NAME]) && !preg_match(static::TOOL_NAME_PATTERN, $tool[McpSchemaKeys::NAME])) {
            $errors[] = sprintf('MCP tool name "%s" must have 1 to 128 letters, digits, "_", "-" or "." in %s', $tool[McpSchemaKeys::NAME], $sourceFile);
        }

        if (isset($tool[McpSchemaKeys::STRUCTURED_CONTENT]) && !is_bool($tool[McpSchemaKeys::STRUCTURED_CONTENT])) {
            $errors[] = sprintf('Operation "%s" mcp structuredContent must be a boolean in %s', $operationKey, $sourceFile);
        }

        if (!isset($tool[McpSchemaKeys::ANNOTATIONS])) {
            return $errors;
        }

        if (!is_array($tool[McpSchemaKeys::ANNOTATIONS]) || array_is_list($tool[McpSchemaKeys::ANNOTATIONS])) {
            $errors[] = sprintf('Operation "%s" mcp annotations must be a map in %s', $operationKey, $sourceFile);

            return $errors;
        }

        return array_merge($errors, $this->validateAnnotations($operationKey, $tool[McpSchemaKeys::ANNOTATIONS], $sourceFile));
    }

    /**
     * @param array<string, mixed> $annotations
     *
     * @return array<string>
     */
    protected function validateAnnotations(string $operationKey, array $annotations, string $sourceFile): array
    {
        $errors = [];

        foreach ($annotations as $key => $value) {
            $error = $this->validateAnnotation($operationKey, (string)$key, $value, $sourceFile);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    protected function validateAnnotation(string $operationKey, string $key, mixed $value, string $sourceFile): ?string
    {
        if ($key === static::ANNOTATION_TITLE) {
            return is_string($value) ? null : sprintf('Operation "%s" mcp annotation title must be a string in %s', $operationKey, $sourceFile);
        }

        if (!in_array($key, static::ANNOTATION_HINT_KEYS, true)) {
            return sprintf('Operation "%s" mcp has the unknown annotation "%s" in %s', $operationKey, $key, $sourceFile);
        }

        return is_bool($value) ? null : sprintf('Operation "%s" mcp annotation %s must be a boolean in %s', $operationKey, $key, $sourceFile);
    }
}
