<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Generator\Template\TemplateRendererInterface;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Spryker\ApiPlatform\Utility\ResourceNameNormalizer;

class ClassGenerator implements ClassGeneratorInterface
{
    protected const string GENERATED_NAMESPACE_PREFIX = 'Generated\Api';

    /**
     * @var array<string, string>
     */
    protected const array TYPE_MAPPING = [
        'string' => 'string',
        'integer' => 'int',
        'boolean' => 'bool',
        'array' => 'array',
        'object' => 'object',
        'mixed' => 'mixed',
    ];

    public function __construct(
        protected readonly TemplateRendererInterface $templateRenderer,
        protected readonly ValidationGroupMapperInterface $validationGroupMapper,
    ) {
    }

    /**
     * Generates a PHP resource class from a schema.
     *
     * The API type is normalized to ucfirst format for proper namespace and class name
     * generation (e.g., 'Backoffice', 'Storefront').
     *
     * Generated structure:
     * - Namespace: Generated\Api\{ApiType}\
     * - Directory: src/Generated/Api/{ApiType}/
     *
     * @param array<string, mixed> $schema The resource schema definition
     * @param string $apiType The API type (normalized to ucfirst automatically)
     */
    public function generate(array $schema, string $apiType): string
    {
        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

        $resourceName = $schema['name'];
        $className = $this->generateClassName($resourceName, $apiType);
        $namespace = $this->generateNamespace($apiType);
        $properties = $this->transformProperties($schema['properties'] ?? [], $schema['validation'] ?? [], $schema['operations'] ?? [], $resourceName);
        $uses = $this->collectUseStatements($schema, $properties);
        $resourceAttribute = $this->generateResourceAttribute($schema);

        $templateData = [
            'className' => $className,
            'namespace' => $namespace,
            'uses' => $uses,
            'resourceAttribute' => $resourceAttribute,
            'properties' => $properties,
            'metadata' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'sourceFiles' => [$schema['sourceFile'] ?? 'unknown'],
                'validationSourceFiles' => $schema['validationSourceFiles'] ?? [],
            ],
        ];

        return $this->templateRenderer->render($templateData);
    }

    protected function generateClassName(string $resourceName, string $apiType): string
    {
        $resourceName = ResourceNameNormalizer::normalize($resourceName);

        return sprintf('%s%sResource', $resourceName, $apiType);
    }

    protected function generateNamespace(string $apiType): string
    {
        return sprintf('%s\\%s', static::GENERATED_NAMESPACE_PREFIX, $apiType);
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     *
     * @return array<array{name: string, type: string, phpType: string, attributes: string, description: string}>
     */
    protected function transformProperties(array $properties, array $validationSchema, array $operations, string $resourceName): array
    {
        $transformed = [];

        foreach ($properties as $name => $property) {
            $type = $property['type'] ?? 'string';
            $phpType = $this->mapToPhpType($type);
            $attributes = $this->generatePropertyAttributes($property, $validationSchema, $operations, $name, $resourceName);

            $transformed[] = [
                'name' => $name,
                'type' => $type,
                'phpType' => $phpType,
                'attributes' => $attributes,
                'description' => $property['description'] ?? '',
            ];
        }

        return $transformed;
    }

    protected function mapToPhpType(string $type): string
    {
        return static::TYPE_MAPPING[$type] ?? 'mixed';
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     */
    protected function generatePropertyAttributes(
        array $property,
        array $validationSchema,
        array $operations,
        string $propertyName,
        string $resourceName
    ): string {
        $attributes = [];

        $apiPropertyParts = [];

        if (isset($property['description']) && $property['description'] !== '') {
            $apiPropertyParts[] = sprintf("description: '%s'", addslashes($property['description']));
        }

        if (isset($property['writable']) && $property['writable'] === false) {
            $apiPropertyParts[] = 'writable: false';
        }

        if (isset($property['readable']) && $property['readable'] === false) {
            $apiPropertyParts[] = 'readable: false';
        }

        if (isset($property['identifier']) && $property['identifier'] === true) {
            $apiPropertyParts[] = 'identifier: true';
        }

        if (isset($property['required']) && $property['required'] === true) {
            $apiPropertyParts[] = 'required: true';
        }

        if (isset($property['openapiContext']) && $property['openapiContext'] !== []) {
            $formattedContext = $this->formatOpenapiContext($property['openapiContext']);
            $apiPropertyParts[] = sprintf('openapiContext: %s', $formattedContext);
        }

        if ($apiPropertyParts !== []) {
            $attributes[] = '#[ApiProperty(' . implode(', ', $apiPropertyParts) . ')]';
        }

        $validationAttributes = $this->generateValidationAttributes($validationSchema, $operations, $propertyName, $resourceName);

        if ($validationAttributes !== []) {
            $attributes = array_merge($attributes, $validationAttributes);
        }

        return implode("\n    ", $attributes);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<array{name: string, type: string, phpType: string, attributes: string, description: string}> $properties
     *
     * @return array<string>
     */
    protected function collectUseStatements(array $schema, array $properties): array
    {
        $uses = [];

        $uses[] = 'ApiPlatform\Metadata\ApiResource';

        $hasApiProperty = false;
        $hasValidation = false;

        foreach ($properties as $property) {
            if ($property['attributes'] !== '') {
                if (!$hasApiProperty && str_contains($property['attributes'], '#[ApiProperty')) {
                    $uses[] = 'ApiPlatform\Metadata\ApiProperty';
                    $hasApiProperty = true;
                }

                if (!$hasValidation && str_contains($property['attributes'], '#[Assert\\')) {
                    $uses[] = 'Symfony\Component\Validator\Constraints as Assert';
                    $hasValidation = true;
                }
            }
        }

        $operations = $schema['operations'] ?? [];

        if (isset($operations['Get'])) {
            $uses[] = 'ApiPlatform\Metadata\Get';
        }

        if (isset($operations['GetCollection'])) {
            $uses[] = 'ApiPlatform\Metadata\GetCollection';
        }

        if (isset($operations['Post'])) {
            $uses[] = 'ApiPlatform\Metadata\Post';
        }

        if (isset($operations['Put'])) {
            $uses[] = 'ApiPlatform\Metadata\Put';
        }

        if (isset($operations['Patch'])) {
            $uses[] = 'ApiPlatform\Metadata\Patch';
        }

        if (isset($operations['Delete'])) {
            $uses[] = 'ApiPlatform\Metadata\Delete';
        }

        if (isset($schema['provider']) && $schema['provider'] !== '') {
            $uses[] = $schema['provider'];
        }

        if (isset($schema['processor']) && $schema['processor'] !== '') {
            $uses[] = $schema['processor'];
        }

        return array_unique($uses);
    }

    /**
     * @param array<string, mixed> $schema
     */
    protected function generateResourceAttribute(array $schema): string
    {
        $operations = $schema['operations'] ?? [];
        $operationsParts = [];

        foreach ($operations as $type => $operation) {
            if (is_array($operation)) {
                $operationsParts[] = "new {$type}()";
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
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     *
     * @return array<string>
     */
    protected function generateValidationAttributes(array $validationSchema, array $operations, string $propertyName, string $resourceName): array
    {
        $attributes = [];

        foreach ($operations as $operationType => $operation) {
            $httpMethod = strtolower($operationType);

            if (!isset($validationSchema[$httpMethod][$propertyName])) {
                continue;
            }

            $group = $this->validationGroupMapper->mapOperationToGroup($operationType, $resourceName);
            $constraints = $validationSchema[$httpMethod][$propertyName];

            foreach ($constraints as $constraint) {
                if ($this->isOptionalConstraint($constraint)) {
                    $nestedConstraints = $this->extractNestedConstraintsFromOptional($constraint);

                    foreach ($nestedConstraints as $nestedConstraint) {
                        if ($this->shouldSkipConstraintForOptionalField($nestedConstraint)) {
                            continue;
                        }

                        $attributes[] = $this->generateConstraintAttribute($nestedConstraint, $group);
                    }

                    continue;
                }

                $attributes[] = $this->generateConstraintAttribute($constraint, $group);
            }
        }

        return $attributes;
    }

    protected function generateConstraintAttribute(mixed $constraint, string $group): string
    {
        if (is_string($constraint)) {
            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraint, $group);
        }

        if (!is_array($constraint)) {
            return '';
        }

        $constraintName = (string)array_key_first($constraint);
        $options = $constraint[$constraintName];

        if (!is_array($options)) {
            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraintName, $group);
        }

        $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

        if ($formattedOptions === '') {
            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraintName, $group);
        }

        return sprintf("#[Assert\\%s(%s, groups: ['%s'])]", $constraintName, $formattedOptions, $group);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function formatConstraintOptions(array $options, string $constraintName = ''): string
    {
        $parts = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $formattedArray = $this->formatArrayValue($value);
                $parts[] = sprintf('%s: %s', $key, $formattedArray);

                continue;
            }

            if (is_string($value)) {
                $escapedValue = addslashes($value);
                $parts[] = sprintf("%s: '%s'", $key, $escapedValue);

                continue;
            }

            if (is_bool($value)) {
                $parts[] = sprintf('%s: %s', $key, $value ? 'true' : 'false');

                continue;
            }

            $parts[] = sprintf('%s: %s', $key, $value);
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<mixed> $array
     */
    protected function formatArrayValue(array $array): string
    {
        $items = [];

        foreach ($array as $value) {
            if (is_string($value)) {
                $items[] = sprintf("'%s'", addslashes($value));

                continue;
            }

            if (is_array($value)) {
                $items[] = $this->formatArrayValue($value);

                continue;
            }

            $items[] = (string)$value;
        }

        return '[' . implode(', ', $items) . ']';
    }

    protected function isOptionalConstraint(mixed $constraint): bool
    {
        if (!is_array($constraint)) {
            return false;
        }

        return isset($constraint['Optional']);
    }

    /**
     * @param array<mixed> $constraint
     *
     * @return array<mixed>
     */
    protected function extractNestedConstraintsFromOptional(array $constraint): array
    {
        if (!isset($constraint['Optional']['constraints'])) {
            return [];
        }

        return $constraint['Optional']['constraints'];
    }

    protected function shouldSkipConstraintForOptionalField(mixed $constraint): bool
    {
        if (is_string($constraint) && $constraint === 'NotBlank') {
            return true;
        }

        if (is_array($constraint) && isset($constraint['NotBlank'])) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
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
