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
            $attributeParts[] = 'operations: [' . implode(', ', $operationsParts) . ']';
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

        $this->addOperationUseStatements($schema, $operations, $uses);

        if ($attributeParts === []) {
            return '#[ApiResource]';
        }

        return '#[ApiResource(' . implode(', ', $attributeParts) . ')]';
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
    protected function buildOperationParameters(array $operation): array
    {
        $parameters = [];

        if (isset($operation['uriTemplate']) && is_string($operation['uriTemplate'])) {
            $parameters['uriTemplate'] = $operation['uriTemplate'];
        }

        if (isset($operation['uriVariables']) && is_array($operation['uriVariables'])) {
            $parameters['uriVariables'] = $this->buildUriVariablesParameter($operation['uriVariables']);
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

        if (isset($operation['output']) && is_array($operation['output'])) {
            $parameters['output'] = $operation['output'];
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return string
     */
    protected function formatOperationParameters(array $parameters): string
    {
        if ($parameters === []) {
            return '';
        }

        $parts = [];

        foreach ($parameters as $key => $value) {
            if (($key === 'uriVariables' || $key === 'openapi') && is_string($value)) {
                $parts[] = sprintf('%s: %s', $key, $value);

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

        return implode(', ', $parts);
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
     *
     * @return string
     */
    protected function buildUriVariablesParameter(array $uriVariables): string
    {
        $parts = [];

        foreach ($uriVariables as $variableName => $config) {
            $linkParameters = $this->buildLinkParameters($variableName, $config);
            $linkCode = sprintf('new Link(%s)', implode(', ', $linkParameters));
            $parts[] = sprintf("'%s' => %s", $variableName, $linkCode);
        }

        return '[' . implode(', ', $parts) . ']';
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
            $linkParameters[] = sprintf('fromClass: %s::class', $this->getShortClassName($config['fromClass']));
        }

        if (isset($config['toProperty']) && is_string($config['toProperty'])) {
            $linkParameters[] = sprintf("toProperty: '%s'", $config['toProperty']);
        }

        return $linkParameters;
    }

    protected function getShortClassName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);

        return end($parts);
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
        $baseParameters = $this->buildOperationParameters($operation);

        if (isset($operation['validationGroups']) && is_array($operation['validationGroups'])) {
            $validationGroups = $operation['validationGroups'];
            $baseParameters['validationContext'] = ['groups' => $validationGroups];
        }

        $tags = $this->determineTagsForOperation($schema, $operation);

        $needsOpenApiOperation = in_array($type, ['Post', 'Patch', 'Put'], true);

        if ($needsOpenApiOperation) {
            $openApiOperation = $this->openApiOperationBuilder->generateOpenApiOperation($schema, $operation, $type, $tags);

            if (str_contains($openApiOperation, 'openapi:')) {
                preg_match('/openapi:\s*(.+)\)\)$/', $openApiOperation, $matches);

                if (isset($matches[1])) {
                    $baseParameters['openapi'] = $matches[1] . ')';
                }
            }
        } elseif ($tags !== null && $tags !== []) {
            $baseParameters['openapi'] = $this->buildOpenApiOperationWithTags($tags);
        }

        if ($baseParameters === []) {
            return sprintf('new %s()', $type);
        }

        $parametersString = $this->formatOperationParameters($baseParameters);

        return sprintf('new %s(%s)', $type, $parametersString);
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
     *
     * @return string
     */
    protected function buildOpenApiOperationWithTags(array $tags): string
    {
        $formattedTags = array_map(
            fn (string $tag): string => sprintf("'%s'", str_replace("'", "\\'", $tag)),
            $tags,
        );

        return sprintf('new Operation(tags: [%s])', implode(', ', $formattedTags));
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
        $needsOpenApiImports = false;
        $needsLinkImport = false;
        $needsOperationImport = false;

        $hasSchemaLevelTags = isset($schema['tags']) && is_array($schema['tags']) && $schema['tags'] !== [];

        if (isset($operations['Get'])) {
            $uses[] = 'ApiPlatform\Metadata\Get';

            if (isset($operations['Get']['uriVariables'])) {
                $needsLinkImport = true;
            }

            if (isset($operations['Get']['tags']) || $hasSchemaLevelTags) {
                $needsOperationImport = true;
            }
        }

        if (isset($operations['GetCollection'])) {
            $uses[] = 'ApiPlatform\Metadata\GetCollection';

            if (isset($operations['GetCollection']['uriVariables'])) {
                $needsLinkImport = true;
            }

            if (isset($operations['GetCollection']['tags']) || $hasSchemaLevelTags) {
                $needsOperationImport = true;
            }
        }

        if (isset($operations['Post'])) {
            $uses[] = 'ApiPlatform\Metadata\Post';
            $needsOpenApiImports = true;

            if (isset($operations['Post']['tags']) || $hasSchemaLevelTags) {
                $needsOperationImport = true;
            }

            if (isset($operations['Post']['uriVariables'])) {
                $needsLinkImport = true;
            }
        }

        if (isset($operations['Put'])) {
            $uses[] = 'ApiPlatform\Metadata\Put';
            $needsOpenApiImports = true;

            if (isset($operations['Put']['tags']) || $hasSchemaLevelTags) {
                $needsOperationImport = true;
            }

            if (isset($operations['Put']['uriVariables'])) {
                $needsLinkImport = true;
            }
        }

        if (isset($operations['Patch'])) {
            $uses[] = 'ApiPlatform\Metadata\Patch';
            $needsOpenApiImports = true;

            if (isset($operations['Patch']['tags']) || $hasSchemaLevelTags) {
                $needsOperationImport = true;
            }

            if (isset($operations['Patch']['uriVariables'])) {
                $needsLinkImport = true;
            }
        }

        if (isset($operations['Delete'])) {
            $uses[] = 'ApiPlatform\Metadata\Delete';

            if (isset($operations['Delete']['uriVariables'])) {
                $needsLinkImport = true;
            }

            if (isset($operations['Delete']['tags']) || $hasSchemaLevelTags) {
                $needsOperationImport = true;
            }
        }

        if ($needsLinkImport) {
            $uses[] = 'ApiPlatform\Metadata\Link';
        }

        if ($needsOperationImport) {
            $uses[] = 'ApiPlatform\OpenApi\Model\Operation';
        }
    }
}
