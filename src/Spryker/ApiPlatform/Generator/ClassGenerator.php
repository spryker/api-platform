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

    /**
     * @var array<string>
     */
    protected const array COMPOSITE_CONSTRAINTS_WITH_CONSTRAINTS_PARAMETER = [
        'All',
        'Sequentially',
        'Composite',
    ];

    /**
     * @var array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    protected array $fqcnConstraintMap = [];

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
        $this->fqcnConstraintMap = [];

        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

        $resourceName = $schema['name'];
        $className = $this->generateClassName($resourceName, $apiType);
        $namespace = $this->generateNamespace($apiType);

        $this->fqcnConstraintMap = $this->collectFqcnConstraints(
            $schema['validation'] ?? [],
            $schema['operations'] ?? [],
            $schema['properties'] ?? [],
        );

        $properties = $this->transformProperties($schema['properties'] ?? [], $schema['validation'] ?? [], $schema['operations'] ?? [], $resourceName);
        $uses = $this->collectUseStatements($schema, $properties);
        $resourceAttribute = $this->generateResourceAttribute($schema);

        $sourceFiles = $this->extractSourceFiles($schema);

        $templateData = [
            'className' => $className,
            'namespace' => $namespace,
            'uses' => $uses,
            'resourceAttribute' => $resourceAttribute,
            'properties' => $properties,
            'metadata' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'sourceFiles' => $sourceFiles,
                'validationSourceFiles' => $schema['validationSourceFiles'] ?? [],
            ],
        ];

        return $this->templateRenderer->render($templateData);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function extractSourceFiles(array $schema): array
    {
        $sourceFiles = [];

        if (!isset($schema['_metadata']['contributingSources']) || !is_array($schema['_metadata']['contributingSources'])) {
            if (isset($schema['sourceFile'])) {
                return [$schema['sourceFile']];
            }

            return ['unknown'];
        }

        foreach ($schema['_metadata']['contributingSources'] as $source) {
            if (isset($source['files']) && is_array($source['files'])) {
                $sourceFiles = array_merge($sourceFiles, $source['files']);

                continue;
            }

            if (isset($source['file'])) {
                $sourceFiles[] = $source['file'];
            }
        }

        if ($sourceFiles === []) {
            return ['unknown'];
        }

        return $sourceFiles;
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

        foreach ($this->fqcnConstraintMap as $constraintData) {
            $uses[] = $this->formatUseStatement($constraintData);
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
     * @param array{fqcn: string, shortName: string, alias: string}|array $constraintData
     */
    protected function formatUseStatement(array $constraintData): string
    {
        if ($constraintData['alias'] === $constraintData['shortName']) {
            return $constraintData['fqcn'];
        }

        return sprintf('%s as %s', $constraintData['fqcn'], $constraintData['alias']);
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
                $operationsParts[] = $this->generateOperationAttribute($type, $operation);
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
     * @param array<string, mixed> $operation
     */
    protected function generateOperationAttribute(string $type, array $operation): string
    {
        if (!isset($operation['validationGroups']) || !is_array($operation['validationGroups'])) {
            return sprintf('new %s()', $type);
        }

        $validationGroups = $operation['validationGroups'];
        $groupsString = "['" . implode("', '", $validationGroups) . "']";

        return sprintf(
            'new %s(validationContext: [\'groups\' => %s])',
            $type,
            $groupsString,
        );
    }

    /**
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     *
     * @return array<string>
     */
    protected function generateValidationAttributes(array $validationSchema, array $operations, string $propertyName, string $resourceName): array
    {
        $constraintsWithGroups = [];

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

                        $constraintsWithGroups[] = [
                            'constraint' => $nestedConstraint,
                            'group' => $group,
                        ];
                    }

                    continue;
                }

                $constraintsWithGroups[] = [
                    'constraint' => $constraint,
                    'group' => $group,
                ];
            }
        }

        $deduplicatedConstraints = $this->deduplicateConstraintsByGroups($constraintsWithGroups);

        $attributes = [];

        foreach ($deduplicatedConstraints as $constraintData) {
            $attributes[] = $this->generateConstraintAttribute($constraintData['constraint'], $constraintData['groups']);
        }

        return $attributes;
    }

    /**
     * @param array<array{constraint: mixed, group: string}> $constraintsWithGroups
     *
     * @return array<array{constraint: mixed, groups: array<string>}>
     */
    protected function deduplicateConstraintsByGroups(array $constraintsWithGroups): array
    {
        $groupedByConstraint = [];

        foreach ($constraintsWithGroups as $item) {
            $constraint = $item['constraint'];
            $group = $item['group'];

            $key = $this->generateConstraintKey($constraint);

            if (!isset($groupedByConstraint[$key])) {
                $groupedByConstraint[$key] = [
                    'constraint' => $constraint,
                    'groups' => [],
                ];
            }

            $groupedByConstraint[$key]['groups'][] = $group;
        }

        foreach ($groupedByConstraint as $key => $data) {
            $groupedByConstraint[$key]['groups'] = array_values(array_unique($data['groups']));
            sort($groupedByConstraint[$key]['groups']);
        }

        return array_values($groupedByConstraint);
    }

    /**
     * @param mixed $constraint
     */
    protected function generateConstraintKey(mixed $constraint): string
    {
        if (is_string($constraint)) {
            if ($this->isFqcn($constraint)) {
                return $this->normalizeFqcn($constraint);
            }

            return $constraint;
        }

        if (!is_array($constraint)) {
            return 'unknown_' . md5(serialize($constraint));
        }

        $constraintName = (string)array_key_first($constraint);
        $normalizedName = $this->isFqcn($constraintName) ? $this->normalizeFqcn($constraintName) : $constraintName;
        $options = $constraint[$constraintName];

        if (!is_array($options)) {
            return $normalizedName;
        }

        return $normalizedName . '_' . md5(serialize($options));
    }

    /**
     * @param array<string> $groups
     */
    protected function generateConstraintAttribute(mixed $constraint, array $groups): string
    {
        $groupsString = implode("', '", $groups);

        if (is_string($constraint)) {
            if ($this->isFqcn($constraint)) {
                $normalized = $this->normalizeFqcn($constraint);
                $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraint)['shortName'];

                return sprintf("#[%s(groups: ['%s'])]", $alias, $groupsString);
            }

            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraint, $groupsString);
        }

        if (!is_array($constraint)) {
            return '';
        }

        $constraintName = (string)array_key_first($constraint);
        $options = $constraint[$constraintName];

        if ($this->isFqcn($constraintName)) {
            $normalized = $this->normalizeFqcn($constraintName);
            $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraintName)['shortName'];

            if (!is_array($options)) {
                return sprintf("#[%s(groups: ['%s'])]", $alias, $groupsString);
            }

            $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

            if ($formattedOptions === '') {
                return sprintf("#[%s(groups: ['%s'])]", $alias, $groupsString);
            }

            return sprintf("#[%s(%s, groups: ['%s'])]", $alias, $formattedOptions, $groupsString);
        }

        if (!is_array($options)) {
            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraintName, $groupsString);
        }

        $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

        if ($formattedOptions === '') {
            return sprintf("#[Assert\\%s(groups: ['%s'])]", $constraintName, $groupsString);
        }

        return sprintf("#[Assert\\%s(%s, groups: ['%s'])]", $constraintName, $formattedOptions, $groupsString);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function formatConstraintOptions(array $options, string $constraintName = ''): string
    {
        $parts = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                if ($key === 'constraints' && in_array($constraintName, static::COMPOSITE_CONSTRAINTS_WITH_CONSTRAINTS_PARAMETER, true)) {
                    $formattedConstraints = $this->formatNestedConstraints($value);
                    $parts[] = sprintf('%s: %s', $key, $formattedConstraints);

                    continue;
                }

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
     * @param array<mixed> $constraints
     */
    protected function formatNestedConstraints(array $constraints): string
    {
        $formattedConstraints = [];

        foreach ($constraints as $constraint) {
            $formattedConstraint = $this->formatSingleNestedConstraint($constraint);

            if ($formattedConstraint !== '') {
                $formattedConstraints[] = $formattedConstraint;
            }
        }

        return '[' . implode(', ', $formattedConstraints) . ']';
    }

    protected function formatSingleNestedConstraint(mixed $constraint): string
    {
        if (is_string($constraint)) {
            if ($this->isFqcn($constraint)) {
                $normalized = $this->normalizeFqcn($constraint);
                $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraint)['shortName'];

                return sprintf('new %s()', $alias);
            }

            return sprintf('new Assert\%s()', $constraint);
        }

        if (!is_array($constraint)) {
            return '';
        }

        $constraintName = (string)array_key_first($constraint);
        $options = $constraint[$constraintName];

        if ($this->isFqcn($constraintName)) {
            $normalized = $this->normalizeFqcn($constraintName);
            $alias = $this->fqcnConstraintMap[$normalized]['alias'] ?? $this->parseConstraintFqcn($constraintName)['shortName'];

            if (!is_array($options)) {
                return sprintf('new %s()', $alias);
            }

            $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

            if ($formattedOptions === '') {
                return sprintf('new %s()', $alias);
            }

            return sprintf('new %s(%s)', $alias, $formattedOptions);
        }

        if (!is_array($options)) {
            return sprintf('new Assert\%s()', $constraintName);
        }

        $formattedOptions = $this->formatConstraintOptions($options, $constraintName);

        if ($formattedOptions === '') {
            return sprintf('new Assert\%s()', $constraintName);
        }

        return sprintf('new Assert\%s(%s)', $constraintName, $formattedOptions);
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

    protected function normalizeFqcn(string $fqcn): string
    {
        return ltrim($fqcn, '\\');
    }

    protected function isFqcn(string $constraintName): bool
    {
        return str_contains($constraintName, '\\');
    }

    /**
     * @return array{namespace: string, shortName: string, namespaceParts: array<string>}
     */
    protected function parseConstraintFqcn(string $fqcn): array
    {
        $normalized = $this->normalizeFqcn($fqcn);
        $parts = explode('\\', $normalized);
        $shortName = array_pop($parts);

        return [
            'namespace' => implode('\\', $parts),
            'shortName' => $shortName,
            'namespaceParts' => $parts,
        ];
    }

    /**
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     * @param array<string, mixed> $properties
     *
     * @return array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    protected function collectFqcnConstraints(array $validationSchema, array $operations, array $properties): array
    {
        $fqcnConstraints = [];

        foreach ($operations as $operationType => $operation) {
            $httpMethod = strtolower($operationType);

            if (!isset($validationSchema[$httpMethod])) {
                continue;
            }

            foreach ($validationSchema[$httpMethod] as $propertyName => $constraints) {
                foreach ($constraints as $constraint) {
                    $this->extractFqcnFromConstraint($constraint, $fqcnConstraints);
                }
            }
        }

        return $this->resolveFqcnConstraintAliases($fqcnConstraints);
    }

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string}> $fqcnConstraints
     */
    protected function extractFqcnFromConstraint(mixed $constraint, array &$fqcnConstraints): void
    {
        if (is_string($constraint) && $this->isFqcn($constraint)) {
            $parsed = $this->parseConstraintFqcn($constraint);
            $normalized = $this->normalizeFqcn($constraint);

            if (!isset($fqcnConstraints[$normalized])) {
                $fqcnConstraints[$normalized] = [
                    'fqcn' => $normalized,
                    'shortName' => $parsed['shortName'],
                    'alias' => $parsed['shortName'],
                    'namespaceParts' => $parsed['namespaceParts'],
                ];
            }

            return;
        }

        if (!is_array($constraint)) {
            return;
        }

        $constraintName = (string)array_key_first($constraint);

        if ($this->isFqcn($constraintName)) {
            $parsed = $this->parseConstraintFqcn($constraintName);
            $normalized = $this->normalizeFqcn($constraintName);

            if (!isset($fqcnConstraints[$normalized])) {
                $fqcnConstraints[$normalized] = [
                    'fqcn' => $normalized,
                    'shortName' => $parsed['shortName'],
                    'alias' => $parsed['shortName'],
                    'namespaceParts' => $parsed['namespaceParts'],
                ];
            }

            return;
        }

        $options = $constraint[$constraintName];

        if (is_array($options) && isset($options['constraints'])) {
            foreach ($options['constraints'] as $nestedConstraint) {
                $this->extractFqcnFromConstraint($nestedConstraint, $fqcnConstraints);
            }
        }
    }

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     *
     * @return array<string, array<string>>
     */
    protected function groupConstraintsByShortName(array $fqcnConstraints): array
    {
        $shortNameMap = [];

        foreach ($fqcnConstraints as $normalized => $data) {
            $shortName = $data['shortName'];

            if (!isset($shortNameMap[$shortName])) {
                $shortNameMap[$shortName] = [];
            }

            $shortNameMap[$shortName][] = $normalized;
        }

        return $shortNameMap;
    }

    /**
     * @param array<string> $fqcns
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     *
     * @return array<string, array<string>>
     */
    protected function groupByVendor(array $fqcns, array $fqcnConstraints): array
    {
        $vendorGroups = [];

        foreach ($fqcns as $fqcn) {
            $vendor = $fqcnConstraints[$fqcn]['namespaceParts'][0] ?? '';

            if (!isset($vendorGroups[$vendor])) {
                $vendorGroups[$vendor] = [];
            }

            $vendorGroups[$vendor][] = $fqcn;
        }

        return $vendorGroups;
    }

    /**
     * @param array<string, array<string>> $vendorGroups
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     */
    protected function resolveVendorGroupAliases(array $vendorGroups, string $shortName, array &$fqcnConstraints): void
    {
        foreach ($vendorGroups as $vendor => $vendorFqcns) {
            if ($vendor === 'Symfony') {
                continue;
            }

            $this->resolveVendorAliases($vendorFqcns, $vendor, $shortName, $fqcnConstraints);
        }
    }

    /**
     * @param array<string> $vendorFqcns
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     */
    protected function resolveVendorAliases(array $vendorFqcns, string $vendor, string $shortName, array &$fqcnConstraints): void
    {
        if (count($vendorFqcns) === 1) {
            $fqcnConstraints[$vendorFqcns[0]]['alias'] = sprintf('%s%s', $vendor, $shortName);

            return;
        }

        $this->resolveVendorCollisionAliases($vendorFqcns, $vendor, $shortName, $fqcnConstraints);
    }

    /**
     * @param array<string> $vendorFqcns
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     */
    protected function resolveVendorCollisionAliases(array $vendorFqcns, string $vendor, string $shortName, array &$fqcnConstraints): void
    {
        foreach ($vendorFqcns as $fqcn) {
            $namespaceParts = $fqcnConstraints[$fqcn]['namespaceParts'];
            $disambiguatingPart = $this->extractDisambiguatingPart($namespaceParts);
            $fqcnConstraints[$fqcn]['alias'] = sprintf('%s%s%s', $vendor, $disambiguatingPart, $shortName);
        }
    }

    /**
     * @param array<string> $namespaceParts
     */
    protected function extractDisambiguatingPart(array $namespaceParts): string
    {
        if (count($namespaceParts) >= 3) {
            return $namespaceParts[2];
        }

        if (count($namespaceParts) >= 2) {
            return $namespaceParts[1];
        }

        return '';
    }

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     *
     * @return array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    protected function formatResolvedConstraints(array $fqcnConstraints): array
    {
        $result = [];

        foreach ($fqcnConstraints as $data) {
            $result[$data['fqcn']] = [
                'fqcn' => $data['fqcn'],
                'shortName' => $data['shortName'],
                'alias' => $data['alias'],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
     *
     * @return array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    protected function resolveFqcnConstraintAliases(array $fqcnConstraints): array
    {
        $shortNameMap = $this->groupConstraintsByShortName($fqcnConstraints);

        foreach ($shortNameMap as $shortName => $fqcns) {
            if (count($fqcns) === 1) {
                continue;
            }

            $vendorGroups = $this->groupByVendor($fqcns, $fqcnConstraints);
            $this->resolveVendorGroupAliases($vendorGroups, $shortName, $fqcnConstraints);
        }

        return $this->formatResolvedConstraints($fqcnConstraints);
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
