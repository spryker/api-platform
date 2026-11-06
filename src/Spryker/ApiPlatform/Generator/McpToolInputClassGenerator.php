<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Schema\McpToolSchemaResolver;

/**
 * Generates one input class for each MCP tool of a resource. The properties are the query
 * parameters (`openapiContext.parameters` with `in: query`) and the URI variables of the operation,
 * so the tool arguments and the REST operation share one declaration. A query parameter name that is
 * not a valid PHP property name (`page[limit]`) becomes a camel case argument name (`pageLimit`).
 */
class McpToolInputClassGenerator
{
    public const string INPUT_CLASSES = 'inputClasses';

    public const string CLASSES = 'classes';

    protected const string CLASS_NAME_SUFFIX = 'McpToolInput';

    /**
     * @uses \Spryker\ApiPlatform\Generator\NestedObjectClassGenerator::GENERATED_NAMESPACE_PREFIX
     */
    protected const string GENERATED_NAMESPACE_PREFIX = 'Generated\\Api';

    protected const string PARAMETER_TYPE_STRING = 'string';

    /**
     * @var array<string>
     */
    protected const array SUPPORTED_PARAMETER_TYPES = ['string', 'integer', 'number', 'boolean', 'array'];

    protected const string OPERATION_TYPE_GET = 'Get';

    protected const string URI_VARIABLE_PATTERN = '/\{([A-Za-z_][A-Za-z0-9_]*)\}/';

    public function __construct(
        protected NestedObjectClassGenerator $nestedObjectClassGenerator,
        protected McpToolSchemaResolver $mcpToolSchemaResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string> $sourceFiles
     *
     * @return array<string, array<string, string>> Keys: `inputClasses` (tool name to input class) and `classes` (class name to code).
     */
    public function generate(array $schema, string $apiType, string $resourceClassBaseName, array $sourceFiles): array
    {
        $inputClasses = [];
        $classes = [];

        foreach ($this->mcpToolSchemaResolver->resolveTools($schema) as $toolName => $mcpToolDefinition) {
            $className = sprintf('%s%s', $this->buildClassBaseName($toolName), static::CLASS_NAME_SUFFIX);

            $classes += $this->nestedObjectClassGenerator->generate(
                $className,
                $this->buildProperties($schema, $mcpToolDefinition->operation),
                $apiType,
                $sourceFiles,
                false,
                $resourceClassBaseName,
                $className,
            );

            $inputClasses[$toolName] = sprintf('%s\\%s\\%s\\%s', static::GENERATED_NAMESPACE_PREFIX, $apiType, $resourceClassBaseName, $className);
        }

        return [
            static::INPUT_CLASSES => $inputClasses,
            static::CLASSES => $classes,
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $operation
     *
     * @return array<string, array<string, mixed>>
     */
    protected function buildProperties(array $schema, array $operation): array
    {
        $properties = [];

        foreach ($this->extractUriVariableNames($schema, $operation) as $uriVariableName) {
            $properties[$uriVariableName] = [
                SchemaKey::TYPE => static::PARAMETER_TYPE_STRING,
                SchemaKey::NULLABLE => true,
                SchemaKey::REQUIRED => true,
            ];
        }

        foreach ($this->mcpToolSchemaResolver->resolveQueryParameters($operation) as $argumentName => $parameter) {
            $properties[$argumentName] = $this->buildQueryParameterProperty($parameter);
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $parameter
     *
     * @return array<string, mixed>
     */
    protected function buildQueryParameterProperty(array $parameter): array
    {
        $type = $parameter['schema'][SchemaKey::TYPE] ?? static::PARAMETER_TYPE_STRING;

        $property = [
            SchemaKey::TYPE => in_array($type, static::SUPPORTED_PARAMETER_TYPES, true) ? $type : static::PARAMETER_TYPE_STRING,
            SchemaKey::NULLABLE => true,
            SchemaKey::REQUIRED => ($parameter['required'] ?? false) === true,
        ];

        if (isset($parameter[SchemaKey::DESCRIPTION]) && is_string($parameter[SchemaKey::DESCRIPTION])) {
            $property[SchemaKey::DESCRIPTION] = $parameter[SchemaKey::DESCRIPTION];
        }

        if (isset($parameter['example'])) {
            $property[SchemaKey::OPEN_API_CONTEXT] = ['example' => $parameter['example']];
        }

        return $property;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $operation
     *
     * @return array<string>
     */
    protected function extractUriVariableNames(array $schema, array $operation): array
    {
        $uriVariables = $operation[SchemaKey::URI_VARIABLES] ?? null;

        if (is_array($uriVariables) && $uriVariables !== []) {
            return array_map('strval', array_is_list($uriVariables) ? $uriVariables : array_keys($uriVariables));
        }

        if (!isset($operation[SchemaKey::URI_TEMPLATE]) && ($operation[SchemaKey::TYPE] ?? null) === static::OPERATION_TYPE_GET) {
            return $this->extractIdentifierNames($schema);
        }

        preg_match_all(static::URI_VARIABLE_PATTERN, (string)($operation[SchemaKey::URI_TEMPLATE] ?? ''), $matches);

        return $matches[1];
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function extractIdentifierNames(array $schema): array
    {
        $identifierNames = [];

        foreach ($schema[SchemaKey::PROPERTIES] ?? [] as $propertyName => $property) {
            if (is_array($property) && ($property[SchemaKey::IDENTIFIER] ?? false) === true) {
                $identifierNames[] = (string)$propertyName;
            }
        }

        return $identifierNames;
    }

    protected function buildClassBaseName(string $toolName): string
    {
        return str_replace(' ', '', ucwords((string)preg_replace('/[^A-Za-z0-9]+/', ' ', $toolName)));
    }
}
