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
 * The `openapi` argument of every operation (tags, summary, parameters, responses, request body
 * examples for Post, Patch, Put) is delegated to {@see \Spryker\ApiPlatform\Generator\OpenApiOperationBuilder}.
 */
class ResourceAttributeGenerator
{
    protected const int OPERATION_PARAM_INDENT_LEVEL = 3;

    /**
     * @var array<string, string>
     */
    protected const array OPEN_API_MODEL_IMPORTS = [
        'new Operation(' => 'ApiPlatform\OpenApi\Model\Operation',
        'new Parameter(' => 'ApiPlatform\OpenApi\Model\Parameter',
        'new Example(' => 'ApiPlatform\OpenApi\Model\Example',
        'new Response(' => 'ApiPlatform\OpenApi\Model\Response',
        'new RequestBody(' => 'ApiPlatform\OpenApi\Model\RequestBody',
        'new MediaType(' => 'ApiPlatform\OpenApi\Model\MediaType',
        'new ArrayObject(' => 'ArrayObject',
    ];

    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader::EXTRA_PROPERTY_DECLARED_RESPONSES
     */
    protected const string EXTRA_PROPERTY_DECLARED_RESPONSES = 'declaredResponses';

    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader::EXTRA_PROPERTY_INTERNAL
     */
    protected const string EXTRA_PROPERTY_INTERNAL = 'internal';

    public function __construct(protected OpenApiOperationBuilder $openApiOperationBuilder)
    {
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string> $uses
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

        if (isset($schema[SchemaKey::PROVIDER]) && $schema[SchemaKey::PROVIDER] !== '') {
            $providerShortName = $this->extractShortClassName($schema[SchemaKey::PROVIDER]);
            $attributeParts[] = sprintf('provider: %s::class', $providerShortName);
        }

        if (isset($schema[SchemaKey::PROCESSOR]) && $schema[SchemaKey::PROCESSOR] !== '') {
            $processorShortName = $this->extractShortClassName($schema[SchemaKey::PROCESSOR]);
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

        if (isset($schema['resourceAttributesClassName']) && is_string($schema['resourceAttributesClassName'])) {
            $extraProperties['resourceAttributesClassName'] = $schema['resourceAttributesClassName'];
        }

        if (isset($schema['includedSortPriority']) && is_numeric($schema['includedSortPriority'])) {
            $extraProperties['includedSortPriority'] = (int)$schema['includedSortPriority'];
        }

        if ($extraProperties !== []) {
            $attributeParts[] = sprintf('extraProperties: %s', $this->formatArrayParameter($extraProperties));
        }

        if (isset($schema[SchemaKey::OPEN_API_CONTEXT]) && $schema[SchemaKey::OPEN_API_CONTEXT] !== []) {
            $attributeParts[] = sprintf('openapiContext: %s', $this->formatArrayParameter($schema[SchemaKey::OPEN_API_CONTEXT]));
        }

        $this->addOperationUseStatements($operations, $uses, implode("\n", $operationsParts));

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

        if (isset($operation['requirements']) && is_array($operation['requirements']) && $operation['requirements'] !== []) {
            $parameters['requirements'] = $operation['requirements'];
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

        if (isset($operation[SchemaKey::PROVIDER]) && is_string($operation[SchemaKey::PROVIDER])) {
            $parameters['provider'] = $operation[SchemaKey::PROVIDER];
        }

        if (isset($operation[SchemaKey::PROCESSOR]) && is_string($operation[SchemaKey::PROCESSOR])) {
            $parameters['processor'] = $operation[SchemaKey::PROCESSOR];
        }

        if (isset($operation[SchemaKey::CONTROLLER]) && is_string($operation[SchemaKey::CONTROLLER])) {
            $parameters['controller'] = $operation[SchemaKey::CONTROLLER];
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

        if (isset($operation['denormalizationContext']) && is_array($operation['denormalizationContext'])) {
            $parameters['denormalizationContext'] = $operation['denormalizationContext'];
        }

        if (isset($operation['status']) && is_int($operation['status'])) {
            $parameters['status'] = $operation['status'];
        }

        if (array_key_exists('read', $operation) && is_bool($operation['read'])) {
            $parameters['read'] = $operation['read'];
        }

        $extraProperties = is_array($operation[SchemaKey::EXTRA_PROPERTIES] ?? null) ? $operation[SchemaKey::EXTRA_PROPERTIES] : [];

        if (($operation[SchemaKey::INTERNAL] ?? null) === true) {
            $extraProperties[static::EXTRA_PROPERTY_INTERNAL] = true;
        }

        if ($extraProperties !== []) {
            $parameters['extraProperties'] = $extraProperties;
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
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

            if (($key === 'provider' || $key === 'processor' || $key === 'controller') && is_string($value)) {
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
     * @param array<string, mixed> $operation
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

        if (($operation[SchemaKey::OPEN_API] ?? null) === false) {
            $baseParameters['openapi'] = false;
            $baseParameters['extraProperties'] = $this->addDeclaredResponsesExtraProperty(
                $operation,
                is_array($baseParameters['extraProperties'] ?? null) ? $baseParameters['extraProperties'] : [],
            );

            if ($baseParameters['extraProperties'] === []) {
                unset($baseParameters['extraProperties']);
            }

            return $this->formatOperationAttribute($operationClass, $baseParameters);
        }

        $openApiOperation = $this->openApiOperationBuilder->generateOpenApiOperation(
            $schema,
            $operation,
            $operationClass,
            $this->determineTagsForOperation($schema, $operation),
            static::OPERATION_PARAM_INDENT_LEVEL,
        );

        if ($openApiOperation !== '') {
            $baseParameters['openapi'] = $openApiOperation;
        }

        return $this->formatOperationAttribute($operationClass, $baseParameters);
    }

    /**
     * `openapi: false` and "here are the statuses I answer" are not contradictory statements, but
     * API Platform's `openapi` argument holds either `false` or an Operation object, never both. A
     * hidden operation would therefore lose its declared responses entirely, and the contract
     * coverage gate would report both a missing declaration and a stale test claim for every test
     * that correctly pins the legacy status. The statuses ride in `extraProperties` instead, which
     * API Platform ignores and {@see \Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader}
     * reads back.
     *
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $extraProperties
     *
     * @return array<string, mixed>
     */
    protected function addDeclaredResponsesExtraProperty(array $operation, array $extraProperties): array
    {
        $responses = $operation[SchemaKey::OPEN_API_CONTEXT][SchemaKey::RESPONSES] ?? null;

        if (!is_array($responses) || $responses === []) {
            return $extraProperties;
        }

        $statuses = array_map(
            static fn (int|string $status): int => (int)$status,
            array_keys($responses),
        );
        sort($statuses);

        $extraProperties[static::EXTRA_PROPERTY_DECLARED_RESPONSES] = array_values(array_unique($statuses));

        return $extraProperties;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function formatOperationAttribute(string $operationClass, array $parameters): string
    {
        if ($parameters === []) {
            return sprintf('new %s()', $operationClass);
        }

        $parametersString = $this->formatOperationParameters($parameters, static::OPERATION_PARAM_INDENT_LEVEL);

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
     * @param array<string, mixed> $operations
     * @param array<string> $uses
     */
    protected function addOperationUseStatements(array $operations, array &$uses, string $operationsContent): void
    {
        $needsLinkImport = false;
        $needsRequestBodyImport = false;

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

            if (isset($operation[SchemaKey::OPEN_API_CONTEXT][SchemaKey::REQUEST_BODY])) {
                $needsRequestBodyImport = true;
            }
        }

        if ($needsLinkImport) {
            $uses[] = 'ApiPlatform\Metadata\Link';
        }

        foreach (static::OPEN_API_MODEL_IMPORTS as $instantiation => $fullyQualifiedClassName) {
            if (str_contains($operationsContent, $instantiation) && !in_array($fullyQualifiedClassName, $uses, true)) {
                $uses[] = $fullyQualifiedClassName;
            }
        }

        if ($needsRequestBodyImport) {
            $uses[] = 'ApiPlatform\OpenApi\Model\RequestBody';
            $uses[] = 'ArrayObject';
        }

        $this->collectOperationServiceUseStatements($operations, $uses);
    }

    /**
     * @param array<string, mixed> $operations
     * @param array<string> $uses
     */
    protected function collectOperationServiceUseStatements(array $operations, array &$uses): void
    {
        $collected = [];

        foreach ($operations as $operation) {
            foreach (['provider', 'processor', 'controller'] as $serviceKey) {
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
