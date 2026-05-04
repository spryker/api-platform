<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * Generates the class-level #[ApiResource(...)] attribute for API Platform resource classes.
 *
 * Transforms resource-level schema configuration into ApiResource PHP attribute syntax,
 * including operations, provider/processor references, pagination, and descriptions.
 *
 * Input schema excerpt:
 * ```php
 * [
 *     'shortName' => 'customers',
 *     'operations' => [
 *         'Get' => ['validationGroups' => ['customers:read']],
 *         'Post' => ['validationGroups' => ['customers:create'], ...],
 *     ],
 *     'provider' => 'Spryker\Glue\Customer\Api\Storefront\Provider\CustomersStorefrontProvider',
 *     'processor' => 'Spryker\Glue\Customer\Api\Storefront\Processor\CustomersStorefrontProcessor',
 *     'description' => 'Customer profile management',
 *     'paginationItemsPerPage' => 10,
 * ]
 * ```
 *
 * Generated output:
 * ```php
 * #[ApiResource(
 *     operations: [
 *         new Get(),
 *         new Post(
 *             validationContext: ['groups' => ['customers:create']],
 *             openapi: new Operation(requestBody: new RequestBody(...))
 *         ),
 *     ],
 *     shortName: 'customers',
 *     provider: CustomersStorefrontProvider::class,
 *     processor: CustomersStorefrontProcessor::class,
 *     description: 'Customer profile management',
 *     paginationItemsPerPage: 10
 * )]
 * ```
 *
 * Handles OpenAPI operation generation for write operations (Post, Patch, Put) with request body examples.
 */
class ResourceAttributeGenerator
{
    protected const int OPERATION_PARAM_INDENT_LEVEL = 3;

    public function __construct(protected OpenApiOperationBuilder $openApiOperationBuilder)
    {
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string> $uses
     *
     * @return string
     */
    public function generate(array $schema, array &$uses): string
    {
        $operations = $schema['operations'] ?? [];
        $operationsParts = [];

        foreach ($operations as $type => $operation) {
            if (is_array($operation)) {
                $operationsParts[] = $this->generateOperationAttribute($schema, $type, $operation);
            }
        }

        $attributeParts = [];

        if ($operationsParts !== []) {
            $indent2 = $this->indent(2);
            $operationsContent = $indent2 . implode(",\n" . $indent2, $operationsParts);
            $attributeParts[] = sprintf("operations: [\n%s,\n%s]", $operationsContent, $this->indent(1));
        } elseif (array_key_exists('operations', $schema)) {
            $attributeParts[] = 'operations: []';
        }

        if (isset($schema['shortName']) && $schema['shortName'] !== '') {
            $attributeParts[] = sprintf("shortName: '%s'", $schema['shortName']);
        }

        if (isset($schema['provider']) && $schema['provider'] !== '') {
            $providerShortName = $this->extractShortClassName($schema['provider']);
            $attributeParts[] = sprintf('provider: %s::class', $providerShortName);
        }

        if (isset($schema['processor']) && $schema['processor'] !== '') {
            $processorShortName = $this->extractShortClassName($schema['processor']);
            $attributeParts[] = sprintf('processor: %s::class', $processorShortName);
        }

        if (isset($schema['description']) && $schema['description'] !== '') {
            $description = addslashes($schema['description']);
            $attributeParts[] = sprintf("description: '%s'", $description);
        }

        if (isset($schema['paginationItemsPerPage'])) {
            $attributeParts[] = sprintf('paginationItemsPerPage: %d', $schema['paginationItemsPerPage']);
        }

        if (isset($schema['paginationEnabled'])) {
            $attributeParts[] = sprintf('paginationEnabled: %s', $schema['paginationEnabled'] ? 'true' : 'false');
        }

        if (isset($schema['paginationMaximumItemsPerPage'])) {
            $attributeParts[] = sprintf('paginationMaximumItemsPerPage: %d', $schema['paginationMaximumItemsPerPage']);
        }

        if (isset($schema['paginationClientEnabled'])) {
            $attributeParts[] = sprintf('paginationClientEnabled: %s', $schema['paginationClientEnabled'] ? 'true' : 'false');
        }

        if (isset($schema['paginationClientItemsPerPage'])) {
            $attributeParts[] = sprintf('paginationClientItemsPerPage: %s', $schema['paginationClientItemsPerPage'] ? 'true' : 'false');
        }

        if (isset($schema['security']) && is_string($schema['security'])) {
            $securityExpression = $schema['security'];

            if (!empty($schema['securityAnonymousAuthRequired'])) {
                $securityExpression .= " or request.headers.has('X-Anonymous-Customer-Unique-Id')";
            }

            $escapedSecurity = str_replace("'", "\\'", $securityExpression);
            $attributeParts[] = sprintf("security: '%s'", $escapedSecurity);
        }

        if (isset($schema['securityMessage']) && is_string($schema['securityMessage'])) {
            $escapedSecurityMessage = str_replace("'", "\\'", $schema['securityMessage']);
            $attributeParts[] = sprintf("securityMessage: '%s'", $escapedSecurityMessage);
        }

        if (isset($schema['securityPostDenormalize']) && is_string($schema['securityPostDenormalize'])) {
            $escapedSecurityPostDenormalize = str_replace("'", "\\'", $schema['securityPostDenormalize']);
            $attributeParts[] = sprintf("securityPostDenormalize: '%s'", $escapedSecurityPostDenormalize);
        }

        if (isset($schema['securityPostDenormalizeMessage']) && is_string($schema['securityPostDenormalizeMessage'])) {
            $escapedSecurityPostDenormalizeMessage = str_replace("'", "\\'", $schema['securityPostDenormalizeMessage']);
            $attributeParts[] = sprintf("securityPostDenormalizeMessage: '%s'", $escapedSecurityPostDenormalizeMessage);
        }

        if (isset($schema['securityPostValidation']) && is_string($schema['securityPostValidation'])) {
            $escapedSecurityPostValidation = str_replace("'", "\\'", $schema['securityPostValidation']);
            $attributeParts[] = sprintf("securityPostValidation: '%s'", $escapedSecurityPostValidation);
        }

        if (isset($schema['securityPostValidationMessage']) && is_string($schema['securityPostValidationMessage'])) {
            $escapedSecurityPostValidationMessage = str_replace("'", "\\'", $schema['securityPostValidationMessage']);
            $attributeParts[] = sprintf("securityPostValidationMessage: '%s'", $escapedSecurityPostValidationMessage);
        }

        $extraProperties = [];

        if (isset($schema['securityCode']) && is_string($schema['securityCode'])) {
            $extraProperties['securityCode'] = $schema['securityCode'];
        }

        if (isset($schema['securityGetStatusCode']) && is_numeric($schema['securityGetStatusCode'])) {
            $extraProperties['securityGetStatusCode'] = (int)$schema['securityGetStatusCode'];
        }

        if (!empty($schema['securityBearerAuthRequired'])) {
            $extraProperties['securityBearerAuthRequired'] = true;
        }

        if (!empty($schema['securityAnonymousAuthRequired'])) {
            $extraProperties['securityAnonymousAuthRequired'] = true;
        }

        if ($extraProperties !== []) {
            $attributeParts[] = sprintf('extraProperties: %s', $this->formatArrayParameter($extraProperties));
        }

        if (isset($schema['openapiContext']) && $schema['openapiContext'] !== []) {
            $attributeParts[] = sprintf('openapiContext: %s', $this->formatArrayParameter($schema['openapiContext']));
        }

        $this->addOperationUseStatements($schema, $operations, $uses);

        if ($attributeParts === []) {
            return '#[ApiResource]';
        }

        $indent1 = $this->indent(1);
        $content = $indent1 . implode(",\n" . $indent1, $attributeParts);

        return sprintf("#[ApiResource(\n%s,\n)]", $content);
    }

    protected function extractShortClassName(string $fullyQualifiedClassName): string
    {
        $parts = explode('\\', $fullyQualifiedClassName);

        return end($parts);
    }

    /**
     * @param array<string, mixed> $operation
     * @param int $indentLevel
     *
     * @return array<string, mixed>
     */
    protected function buildOperationParameters(array $operation, int $indentLevel): array
    {
        $parameters = [];

        if (isset($operation['uriTemplate']) && is_string($operation['uriTemplate'])) {
            $parameters['uriTemplate'] = $operation['uriTemplate'];
        }

        if (isset($operation['uriVariables']) && is_array($operation['uriVariables'])) {
            $parameters['uriVariables'] = $this->buildUriVariablesParameter($operation['uriVariables'], $indentLevel);
        }

        if (isset($operation['security']) && is_string($operation['security'])) {
            $parameters['security'] = $operation['security'];
        }

        if (isset($operation['securityMessage']) && is_string($operation['securityMessage'])) {
            $parameters['securityMessage'] = $operation['securityMessage'];
        }

        if (isset($operation['description']) && is_string($operation['description'])) {
            $parameters['description'] = $operation['description'];
        }

        if (isset($operation['securityPostDenormalize']) && is_string($operation['securityPostDenormalize'])) {
            $parameters['securityPostDenormalize'] = $operation['securityPostDenormalize'];
        }

        if (isset($operation['securityPostDenormalizeMessage']) && is_string($operation['securityPostDenormalizeMessage'])) {
            $parameters['securityPostDenormalizeMessage'] = $operation['securityPostDenormalizeMessage'];
        }

        if (isset($operation['securityPostValidation']) && is_string($operation['securityPostValidation'])) {
            $parameters['securityPostValidation'] = $operation['securityPostValidation'];
        }

        if (isset($operation['securityPostValidationMessage']) && is_string($operation['securityPostValidationMessage'])) {
            $parameters['securityPostValidationMessage'] = $operation['securityPostValidationMessage'];
        }

        if (isset($operation['provider']) && is_string($operation['provider'])) {
            $parameters['provider'] = $operation['provider'];
        }

        if (isset($operation['processor']) && is_string($operation['processor'])) {
            $parameters['processor'] = $operation['processor'];
        }

        if (array_key_exists('output', $operation)) {
            $parameters['output'] = $operation['output'];
        }

        if (array_key_exists('deserialize', $operation) && is_bool($operation['deserialize'])) {
            $parameters['deserialize'] = $operation['deserialize'];
        }

        if (isset($operation['normalizationContext']) && is_array($operation['normalizationContext'])) {
            $parameters['normalizationContext'] = $operation['normalizationContext'];
        }

        if (isset($operation['status']) && is_int($operation['status'])) {
            $parameters['status'] = $operation['status'];
        }

        if (array_key_exists('read', $operation) && is_bool($operation['read'])) {
            $parameters['read'] = $operation['read'];
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param int $indentLevel
     *
     * @return string
     */
    protected function formatOperationParameters(array $parameters, int $indentLevel): string
    {
        if ($parameters === []) {
            return '';
        }

        $parts = [];
        $indent = $this->indent($indentLevel);

        foreach ($parameters as $key => $value) {
            if (($key === 'uriVariables' || $key === 'openapi') && is_string($value)) {
                $parts[] = sprintf('%s: %s', $key, $value);

                continue;
            }

            if (($key === 'provider' || $key === 'processor') && is_string($value)) {
                $shortName = $this->extractShortClassName($value);
                $parts[] = sprintf('%s: %s::class', $key, $shortName);

                continue;
            }

            if (is_string($value)) {
                $escapedValue = str_replace("'", "\\'", $value);
                $parts[] = sprintf("%s: '%s'", $key, $escapedValue);

                continue;
            }

            if (is_bool($value)) {
                $parts[] = sprintf('%s: %s', $key, $value ? 'true' : 'false');

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $parts[] = sprintf('%s: %s', $key, $value);

                continue;
            }

            if (is_array($value)) {
                $parts[] = sprintf('%s: %s', $key, $this->formatArrayParameter($value));
            }
        }

        return $indent . implode(",\n" . $indent, $parts);
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return string
     */
    protected function formatArrayParameter(array $array): string
    {
        $parts = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $formattedValue = $this->formatArrayParameter($value);
                $parts[] = sprintf("'%s' => %s", $key, $formattedValue);

                continue;
            }

            if (is_string($value)) {
                $parts[] = sprintf("'%s' => '%s'", $key, str_replace("'", "\\'", $value));

                continue;
            }

            $parts[] = sprintf("'%s' => %s", $key, json_encode($value));
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param int $keyIndentLevel The indent level of the uriVariables key
     *
     * @return string
     */
    protected function buildUriVariablesParameter(array $uriVariables, int $keyIndentLevel): string
    {
        if ($uriVariables === []) {
            return '[]';
        }

        $parts = [];
        $itemIndent = $this->indent($keyIndentLevel + 1);
        $linkParamIndent = $this->indent($keyIndentLevel + 2);

        foreach ($uriVariables as $variableName => $config) {
            $linkParameters = $this->buildLinkParameters($variableName, $config ?? []);
            $linkParamsContent = $linkParamIndent . implode(",\n" . $linkParamIndent, $linkParameters);
            $linkCode = sprintf("new Link(\n%s,\n%s)", $linkParamsContent, $itemIndent);
            $parts[] = sprintf("'%s' => %s", $variableName, $linkCode);
        }

        $content = $itemIndent . implode(",\n" . $itemIndent, $parts);

        return sprintf("[\n%s,\n%s]", $content, $this->indent($keyIndentLevel));
    }

    /**
     * @param string $variableName
     * @param array<string, mixed> $config
     *
     * @return array<string>
     */
    protected function buildLinkParameters(string $variableName, array $config): array
    {
        $linkParameters = [];

        $linkParameters[] = sprintf("parameterName: '%s'", $variableName);

        if (isset($config['fromProperty']) && is_string($config['fromProperty'])) {
            $linkParameters[] = sprintf("fromProperty: '%s'", $config['fromProperty']);
        }

        if (isset($config['fromClass']) && is_string($config['fromClass'])) {
            $linkParameters[] = sprintf('fromClass: %s::class', $this->extractShortClassName($config['fromClass']));
        }

        if (isset($config['toProperty']) && is_string($config['toProperty'])) {
            $linkParameters[] = sprintf("toProperty: '%s'", $config['toProperty']);
        }

        if (isset($config['identifiers']) && is_array($config['identifiers'])) {
            $identifiersList = implode("', '", $config['identifiers']);
            $linkParameters[] = sprintf("identifiers: ['%s']", $identifiersList);
        }

        return $linkParameters;
    }

    /**
     * @param array<string, mixed> $schema
     * @param string $type
     * @param array<string, mixed> $operation
     *
     * @return string
     */
    protected function generateOperationAttribute(array $schema, string $type, array $operation): string
    {
        $operationClass = $operation['type'] ?? $type;
        $baseParameters = $this->buildOperationParameters($operation, static::OPERATION_PARAM_INDENT_LEVEL);

        if (isset($operation['name'])) {
            $baseParameters = array_merge(['name' => $operation['name']], $baseParameters);
        }

        if (isset($operation['validationGroups']) && is_array($operation['validationGroups'])) {
            $validationGroups = $operation['validationGroups'];
            $baseParameters['validationContext'] = ['groups' => $validationGroups];
        }

        $tags = $this->determineTagsForOperation($schema, $operation);

        $needsOpenApiOperation = in_array($operationClass, ['Post', 'Patch', 'Put'], true);

        if ($needsOpenApiOperation) {
            $openApiOperation = $this->openApiOperationBuilder->generateOpenApiOperation(
                $schema,
                $operation,
                $operationClass,
                $tags,
                static::OPERATION_PARAM_INDENT_LEVEL,
            );

            if ($openApiOperation !== '') {
                $baseParameters['openapi'] = $openApiOperation;
            }
        } elseif ($tags !== null && $tags !== []) {
            $baseParameters['openapi'] = $this->buildOpenApiOperationWithTags($tags, static::OPERATION_PARAM_INDENT_LEVEL);
        }

        if ($baseParameters === []) {
            return sprintf('new %s()', $operationClass);
        }

        $parametersString = $this->formatOperationParameters($baseParameters, static::OPERATION_PARAM_INDENT_LEVEL);

        return sprintf("new %s(\n%s,\n%s)", $operationClass, $parametersString, $this->indent(2));
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $operation
     *
     * @return array<string>|null
     */
    protected function determineTagsForOperation(array $schema, array $operation): ?array
    {
        if (isset($operation['tags']) && is_array($operation['tags'])) {
            return $operation['tags'];
        }

        if (isset($schema['tags']) && is_array($schema['tags'])) {
            return $schema['tags'];
        }

        return null;
    }

    /**
     * @param array<string> $tags
     * @param int $indentLevel The indent level of the openapi key
     *
     * @return string
     */
    protected function buildOpenApiOperationWithTags(array $tags, int $indentLevel): string
    {
        $formattedTags = array_map(
            fn (string $tag): string => sprintf("'%s'", str_replace("'", "\\'", $tag)),
            $tags,
        );

        $paramIndent = $this->indent($indentLevel + 1);
        $closeIndent = $this->indent($indentLevel);

        return sprintf("new Operation(\n%stags: [%s],\n%s)", $paramIndent, implode(', ', $formattedTags), $closeIndent);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $operations
     * @param array<string> $uses
     *
     * @return void
     */
    protected function addOperationUseStatements(array $schema, array $operations, array &$uses): void
    {
        $needsLinkImport = false;
        $needsOperationImport = false;

        $hasSchemaLevelTags = isset($schema['tags']) && is_array($schema['tags']) && $schema['tags'] !== [];

        $typeImportMap = [
            'Get' => 'ApiPlatform\Metadata\Get',
            'GetCollection' => 'ApiPlatform\Metadata\GetCollection',
            'Post' => 'ApiPlatform\Metadata\Post',
            'Put' => 'ApiPlatform\Metadata\Put',
            'Patch' => 'ApiPlatform\Metadata\Patch',
            'Delete' => 'ApiPlatform\Metadata\Delete',
        ];

        $addedTypes = [];

        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                continue;
            }

            $operationType = $operation['type'] ?? '';

            if (isset($typeImportMap[$operationType]) && !isset($addedTypes[$operationType])) {
                $uses[] = $typeImportMap[$operationType];
                $addedTypes[$operationType] = true;
            }

            if (isset($operation['uriVariables'])) {
                $needsLinkImport = true;
            }

            if (isset($operation['tags']) || $hasSchemaLevelTags) {
                $needsOperationImport = true;
            }
        }

        if ($needsLinkImport) {
            $uses[] = 'ApiPlatform\Metadata\Link';
        }

        if ($needsOperationImport) {
            $uses[] = 'ApiPlatform\OpenApi\Model\Operation';
        }

        $this->collectOperationServiceUseStatements($operations, $uses);
    }

    /**
     * @param array<string, mixed> $operations
     * @param array<string> $uses
     *
     * @return void
     */
    protected function collectOperationServiceUseStatements(array $operations, array &$uses): void
    {
        $collected = [];

        foreach ($operations as $operation) {
            foreach (['provider', 'processor'] as $serviceKey) {
                if (!isset($operation[$serviceKey]) || !is_string($operation[$serviceKey])) {
                    continue;
                }

                $serviceFqcn = $operation[$serviceKey];

                if (!str_contains($serviceFqcn, '\\') || isset($collected[$serviceFqcn])) {
                    continue;
                }

                $collected[$serviceFqcn] = true;
                $uses[] = $serviceFqcn;
            }
        }
    }

    protected function indent(int $level): string
    {
        return str_repeat('    ', $level);
    }
}
