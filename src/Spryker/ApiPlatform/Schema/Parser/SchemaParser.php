<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Parser;

use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Generator\ResourceNameTagGenerator;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;
use Spryker\ApiPlatform\Schema\Validator\PreMergeValidatorInterface;

/**
 * Parses raw YAML resource schemas into normalized arrays ready for code generation.
 *
 * Normalizes operations, properties, includes, and pagination settings, merges
 * validation schemas, and auto-enriches operations with validation groups.
 */
class SchemaParser implements SchemaParserInterface
{
    public function __construct(
        protected readonly PreMergeValidatorInterface $preMergeValidator,
        protected readonly ValidationGroupMapperInterface $validationGroupMapper,
        protected readonly ResourceNameTagGenerator $tagGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $rawSchema
     * @param array<string, mixed> $validationSchemas
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     *
     * @return array<string, mixed>
     */
    public function parse(array $rawSchema, SplFileInfo $file, array $validationSchemas = []): array
    {
        $filePath = $file->getRealPath() ?: $file->getPathname();

        if (!isset($rawSchema['resource'])) {
            throw new ApiSchemaValidationException(
                'Schema must have a "resource" key',
                $filePath,
            );
        }

        $resource = $rawSchema['resource'];

        if (!is_array($resource)) {
            throw new ApiSchemaValidationException(
                'Resource must be an array',
                $filePath,
            );
        }

        $includes = $this->normalizeIncludes($resource);

        $parsedSchema = [
            'name' => $this->getValue($resource, 'name', null),
            'shortName' => $this->getValue($resource, 'shortName', $this->getValue($resource, 'name', null)),
            'codeBucket' => $this->getValue($resource, 'codeBucket', null),
            'description' => $this->getValue($resource, 'description', ''),
            'operations' => $this->normalizeOperations($resource, $filePath),
            'properties' => $this->normalizeProperties($resource, $filePath, $includes),
            'provider' => $this->getValue($resource, 'provider', null),
            'processor' => $this->getValue($resource, 'processor', null),
            'paginationItemsPerPage' => $this->getValue($resource, 'paginationItemsPerPage', null),
            'paginationEnabled' => $this->getValue($resource, 'paginationEnabled', null),
            'paginationMaximumItemsPerPage' => $this->getValue($resource, 'paginationMaximumItemsPerPage', null),
            'paginationClientEnabled' => $this->getValue($resource, 'paginationClientEnabled', null),
            'paginationClientItemsPerPage' => $this->getValue($resource, 'paginationClientItemsPerPage', null),
            'security' => $this->getValue($resource, 'security', null),
            'securityMessage' => $this->getValue($resource, 'securityMessage', null),
            'securityCode' => $this->getValue($resource, 'securityCode', null),
            'securityGetStatusCode' => $this->getValue($resource, 'securityGetStatusCode', null),
            'securityBearerAuthRequired' => $this->getValue($resource, 'securityBearerAuthRequired', null),
            'securityAnonymousAuthRequired' => $this->getValue($resource, 'securityAnonymousAuthRequired', null),
            'securityPostDenormalize' => $this->getValue($resource, 'securityPostDenormalize', null),
            'securityPostDenormalizeMessage' => $this->getValue($resource, 'securityPostDenormalizeMessage', null),
            'securityPostValidation' => $this->getValue($resource, 'securityPostValidation', null),
            'securityPostValidationMessage' => $this->getValue($resource, 'securityPostValidationMessage', null),
            'openapiContext' => $this->getValue($resource, 'openapiContext', []),
            'includes' => $includes,
            'includableIn' => $this->normalizeIncludableIn($resource),
            'sourceFile' => $filePath,
            'sourceLayer' => $this->detectSourceLayer($filePath),
        ];

        $shortName = $parsedSchema['shortName'];

        if (isset($resource['tags']) && is_array($resource['tags'])) {
            $parsedSchema['tags'] = $resource['tags'];
        } elseif ($shortName !== null && $shortName !== '') {
            $parsedSchema['tags'] = $this->tagGenerator->generateTags($shortName);
        }

        $resourceName = $this->generateResourceKey($filePath);

        if (isset($validationSchemas[$resourceName]) && is_array($validationSchemas[$resourceName])) {
            $validationSchemaList = [];
            $validationSourceFiles = [];

            foreach ($validationSchemas[$resourceName] as $validationData) {
                $validationSchemaList[] = $validationData['schema'];
                $validationSourceFiles[] = $validationData['sourceFile'];
            }

            if ($validationSchemaList !== []) {
                $parsedSchema['validation'] = $validationSchemaList;

                $parsedSchema['validationSourceFiles'] = $validationSourceFiles;
            }
        }

        $parsedSchema = $this->enrichOperationsWithValidationGroups($parsedSchema);

        $this->preMergeValidator->validate($parsedSchema, $filePath);

        return $parsedSchema;
    }

    /**
     * Automatically enrich operations with validation groups based on validation schema.
     *
     * @param array<string, mixed> $parsedSchema
     *
     * @return array<string, mixed>
     */
    protected function enrichOperationsWithValidationGroups(array $parsedSchema): array
    {
        if (!isset($parsedSchema['validation']) || !is_array($parsedSchema['validation'])) {
            return $parsedSchema;
        }

        if (!isset($parsedSchema['operations']) || !is_array($parsedSchema['operations'])) {
            return $parsedSchema;
        }

        $resourceName = $parsedSchema['shortName'] ?? $parsedSchema['name'] ?? '';

        if ($resourceName === '') {
            return $parsedSchema;
        }

        $validation = $parsedSchema['validation'];
        $validationOperationKeys = $this->extractValidationOperationKeys($validation);

        if ($validationOperationKeys === []) {
            return $parsedSchema;
        }

        foreach ($parsedSchema['operations'] as $operationKey => $operation) {
            if (isset($operation['validationGroups']) && is_array($operation['validationGroups'])) {
                continue;
            }

            // The array key is the operation name (e.g. `createGuestCartItem`) when explicitly given
            // in the YAML, otherwise the operation type (`Post`, `Patch`, …). Validation YAML files
            // are keyed by HTTP method, so we must match against the type — not the array key —
            // so named operations also receive the auto-generated group.
            $operationType = is_string($operation['type'] ?? null) ? $operation['type'] : (string)$operationKey;
            $validationKey = strtolower($operationType);

            if (!in_array($validationKey, $validationOperationKeys, true)) {
                continue;
            }

            $validationGroup = $this->validationGroupMapper->mapOperationToGroup($operationType, $resourceName);

            $parsedSchema['operations'][$operationKey]['validationGroups'] = [$validationGroup];
        }

        return $parsedSchema;
    }

    /**
     * Extract operation keys from validation schema (post, patch, put, etc.).
     *
     * @param array<int, array<string, mixed>> $schemaFileGroupedOperationValidations
     *
     * @return array<string>
     */
    protected function extractValidationOperationKeys(array $schemaFileGroupedOperationValidations): array
    {
        $knownOperations = ['post', 'patch', 'put', 'get', 'delete', 'getcollection'];
        $operationKeys = [];

        foreach ($schemaFileGroupedOperationValidations as $schemaFileGroupedOperationValidation) {
            foreach (array_keys($schemaFileGroupedOperationValidation) as $operationKey) {
                $normalizedOperationKey = strtolower((string)$operationKey);

                if (in_array($normalizedOperationKey, $knownOperations, true)) {
                    $operationKeys[] = $normalizedOperationKey;
                }
            }
        }

        return array_values(array_unique($operationKeys));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function getValue(array $data, string $key, mixed $default): mixed
    {
        return $data[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     *
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeOperations(array $resource, string $filePath): array
    {
        $operations = $this->getValue($resource, 'operations', []);

        if (!is_array($operations)) {
            throw new ApiSchemaValidationException(
                'Operations must be an array',
                $filePath,
            );
        }

        $normalized = [];

        foreach ($operations as $key => $operation) {
            if (!is_array($operation)) {
                continue;
            }

            $operationName = $operation['name'] ?? (is_string($key) ? $key : ($operation['type'] ?? null));
            $operationType = $operation['type'] ?? $operationName;

            if ($operationType === null) {
                continue;
            }

            $normalizedOperation = ['type' => $operationType];

            if ($operationName !== null && $operationName !== $operationType) {
                $normalizedOperation['name'] = $operationName;
            }

            if (isset($operation['provider']) && is_string($operation['provider'])) {
                $normalizedOperation['provider'] = $operation['provider'];
            }

            if (isset($operation['processor'])) {
                $normalizedOperation['processor'] = $operation['processor'];
            }

            if (isset($operation['validationGroups']) && is_array($operation['validationGroups'])) {
                $normalizedOperation['validationGroups'] = $operation['validationGroups'];
            }

            if (isset($operation['openapiContext']) && is_array($operation['openapiContext'])) {
                $normalizedOperation['openapiContext'] = $operation['openapiContext'];
            }

            if (isset($operation['uriTemplate']) && is_string($operation['uriTemplate'])) {
                $normalizedOperation['uriTemplate'] = $operation['uriTemplate'];
            }

            if (isset($operation['uriVariables']) && is_array($operation['uriVariables'])) {
                $normalizedOperation['uriVariables'] = $operation['uriVariables'];
            }

            if (isset($operation['security']) && is_string($operation['security'])) {
                $normalizedOperation['security'] = $operation['security'];
            }

            if (isset($operation['securityMessage']) && is_string($operation['securityMessage'])) {
                $normalizedOperation['securityMessage'] = $operation['securityMessage'];
            }

            if (isset($operation['description']) && is_string($operation['description'])) {
                $normalizedOperation['description'] = $operation['description'];
            }

            if (isset($operation['securityPostDenormalize']) && is_string($operation['securityPostDenormalize'])) {
                $normalizedOperation['securityPostDenormalize'] = $operation['securityPostDenormalize'];
            }

            if (isset($operation['securityPostDenormalizeMessage']) && is_string($operation['securityPostDenormalizeMessage'])) {
                $normalizedOperation['securityPostDenormalizeMessage'] = $operation['securityPostDenormalizeMessage'];
            }

            if (isset($operation['securityPostValidation']) && is_string($operation['securityPostValidation'])) {
                $normalizedOperation['securityPostValidation'] = $operation['securityPostValidation'];
            }

            if (isset($operation['securityPostValidationMessage']) && is_string($operation['securityPostValidationMessage'])) {
                $normalizedOperation['securityPostValidationMessage'] = $operation['securityPostValidationMessage'];
            }

            if (isset($operation['status']) && is_int($operation['status'])) {
                $normalizedOperation['status'] = $operation['status'];
            }

            if (isset($operation['tags']) && is_array($operation['tags'])) {
                $normalizedOperation['tags'] = $operation['tags'];
            }

            if (array_key_exists('output', $operation)) {
                $normalizedOperation['output'] = $operation['output'];
            }

            if (array_key_exists('deserialize', $operation)) {
                $normalizedOperation['deserialize'] = (bool)$operation['deserialize'];
            }

            if (isset($operation['normalizationContext']) && is_array($operation['normalizationContext'])) {
                $normalizedOperation['normalizationContext'] = $operation['normalizationContext'];
            }

            if (array_key_exists('read', $operation)) {
                $normalizedOperation['read'] = (bool)$operation['read'];
            }

            if (isset($operation['extraProperties']) && is_array($operation['extraProperties'])) {
                $normalizedOperation['extraProperties'] = $operation['extraProperties'];
            }

            $normalized[$operationName ?? $operationType] = $normalizedOperation;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<int, array<string, mixed>> $includes
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     *
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeProperties(array $resource, string $filePath, array $includes = []): array
    {
        $properties = $this->getValue($resource, 'properties', []);

        if (!is_array($properties)) {
            throw new ApiSchemaValidationException(
                'Properties must be an array',
                $filePath,
            );
        }

        foreach ($includes as $include) {
            $relationshipName = $include['relationshipName'] ?? null;
            $targetResource = $include['targetResource'] ?? null;

            if ($relationshipName === null || $targetResource === null) {
                continue;
            }

            // Convert kebab-case relationship names to camelCase for valid PHP property names
            // while keeping the original kebab-case in the relationship config for ?include= URL parameter
            $propertyName = $this->kebabToCamelCase($relationshipName);

            if (isset($properties[$propertyName])) {
                if (!isset($properties[$propertyName]['uriTemplate']) && isset($include['uriTemplate']) && is_string($include['uriTemplate'])) {
                    $properties[$propertyName]['uriTemplate'] = $include['uriTemplate'];
                }

                continue;
            }

            $autoGeneratedProperty = [
                'type' => 'array',
                'writable' => false,
                'readable' => false,
                'required' => false,
                'description' => sprintf('Related %s resources', $targetResource),
            ];

            if ($propertyName !== $relationshipName) {
                $autoGeneratedProperty['serializedName'] = $relationshipName;
            }

            if (isset($include['uriTemplate']) && is_string($include['uriTemplate'])) {
                $autoGeneratedProperty['uriTemplate'] = $include['uriTemplate'];
            }

            $properties[$propertyName] = $autoGeneratedProperty;
        }

        $normalized = [];

        foreach ($properties as $key => $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyName = is_string($key) ? $key : ($property['name'] ?? null);

            if ($propertyName === null) {
                continue;
            }

            $normalized[$propertyName] = ['name' => $propertyName];

            if (isset($property['type'])) {
                $normalized[$propertyName]['type'] = $this->normalizePropertyType($property['type']);
            }

            if (isset($property['description'])) {
                $normalized[$propertyName]['description'] = $property['description'];
            }

            if (isset($property['writable'])) {
                $normalized[$propertyName]['writable'] = $property['writable'];
            }

            if (isset($property['readable'])) {
                $normalized[$propertyName]['readable'] = $property['readable'];
            }

            if (isset($property['identifier'])) {
                $normalized[$propertyName]['identifier'] = $property['identifier'];
            }

            if (isset($property['required'])) {
                $normalized[$propertyName]['required'] = $property['required'];
            }

            if (isset($property['default'])) {
                $normalized[$propertyName]['default'] = $property['default'];
            }

            if (isset($property['openapiContext'])) {
                $normalized[$propertyName]['openapiContext'] = $property['openapiContext'];
            }

            if (isset($property['uriTemplate']) && is_string($property['uriTemplate'])) {
                $normalized[$propertyName]['uriTemplate'] = $property['uriTemplate'];
            }

            if (isset($property['serializedName'])) {
                $normalized[$propertyName]['serializedName'] = $property['serializedName'];
            }

            if (isset($property['serializedPath'])) {
                $normalized[$propertyName]['serializedPath'] = $property['serializedPath'];
            }

            if (isset($property['nullable'])) {
                $normalized[$propertyName]['nullable'] = $property['nullable'];
            }
        }

        return $normalized;
    }

    protected function normalizePropertyType(mixed $type): string
    {
        if (!is_string($type)) {
            return 'string';
        }

        $normalized = strtolower(trim($type));

        // Map common PHP type aliases
        $phpTypeMap = [
            'int' => 'integer',
            'bool' => 'boolean',
            'str' => 'string',
            'arr' => 'array',
            'float' => 'number',
            'double' => 'number',
        ];

        if (isset($phpTypeMap[$normalized])) {
            return $phpTypeMap[$normalized];
        }

        // Known schema types should remain lowercase
        $knownTypes = ['string', 'integer', 'boolean', 'array', 'number', 'object', 'map', 'mixed'];

        if (in_array($normalized, $knownTypes, true)) {
            return $normalized;
        }

        // For unknown types (resource classes), preserve original case
        return trim($type);
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeIncludes(array $resource): array
    {
        $includes = $this->getValue($resource, 'includes', []);

        if (!is_array($includes)) {
            return [];
        }

        $normalized = [];

        foreach ($includes as $include) {
            if (!is_array($include)) {
                continue;
            }

            $relationshipName = $include['relationshipName'] ?? null;
            $targetResource = $include['targetResource'] ?? null;

            if ($relationshipName === null || $targetResource === null) {
                continue;
            }

            $normalizedInclude = [
                'relationshipName' => $relationshipName,
                'targetResource' => $targetResource,
                'uriVariableMappings' => [],
            ];

            if (isset($include['uriVariableMappings']) && is_array($include['uriVariableMappings'])) {
                $normalizedInclude['uriVariableMappings'] = $include['uriVariableMappings'];
            }

            if (isset($include['uriTemplate']) && is_string($include['uriTemplate'])) {
                $normalizedInclude['uriTemplate'] = $include['uriTemplate'];
            }

            if (isset($include['resolverClass']) && (is_string($include['resolverClass']) || $include['resolverClass'] === true)) {
                $normalizedInclude['resolverClass'] = $include['resolverClass'];
            }

            $normalized[] = $normalizedInclude;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeIncludableIn(array $resource): array
    {
        $includableIn = $this->getValue($resource, 'includableIn', []);

        if (!is_array($includableIn)) {
            return [];
        }

        $normalized = [];

        foreach ($includableIn as $includable) {
            if (!is_array($includable)) {
                continue;
            }

            $resourceName = $includable['resource'] ?? null;
            $relationshipName = $includable['relationshipName'] ?? null;

            if ($resourceName === null || $relationshipName === null) {
                continue;
            }

            $normalizedIncludable = [
                'resource' => $resourceName,
                'relationshipName' => $relationshipName,
                'uriVariableMappings' => [],
            ];

            if (isset($includable['uriVariableMappings']) && is_array($includable['uriVariableMappings'])) {
                $normalizedIncludable['uriVariableMappings'] = $includable['uriVariableMappings'];
            }

            $normalized[] = $normalizedIncludable;
        }

        return $normalized;
    }

    protected function detectSourceLayer(string $filePath): string
    {
        if (str_contains($filePath, '/Pyz/')) {
            return 'project';
        }

        if (str_contains($filePath, '/SprykerFeature/')) {
            return 'feature';
        }

        return 'core';
    }

    protected function generateResourceKey(string $filePath): string
    {
        $apiType = $this->extractApiTypeFromPath($filePath);
        $fileName = basename($filePath, '.resource.yml');
        $fileName = basename($fileName, '.resource.yaml');

        return sprintf('%s_%s', $apiType, $fileName);
    }

    protected function extractApiTypeFromPath(string $filePath): string
    {
        if (preg_match('#/resources/api/([^/]+)/#', $filePath, $matches)) {
            return $matches[1];
        }

        return 'backoffice';
    }

    /**
     * Converts kebab-case to camelCase for valid PHP property names.
     *
     * Relationship names use kebab-case in YAML and URL parameters (?include=concrete-products)
     * but must be valid PHP identifiers when used as auto-generated class properties.
     */
    protected function kebabToCamelCase(string $value): string
    {
        if (!str_contains($value, '-')) {
            return $value;
        }

        return lcfirst(str_replace('-', '', ucwords($value, '-')));
    }
}
