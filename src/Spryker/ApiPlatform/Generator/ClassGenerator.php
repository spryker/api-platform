<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Generator\Result\GeneratedResourceResult;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Spryker\ApiPlatform\Utility\ResourceNameNormalizer;

/**
 * Generates complete PHP resource class code from parsed schema definitions.
 *
 * Transforms resource schema arrays into fully-featured API Platform resource classes with
 * properties, validation attributes, getters/setters, and serialization methods.
 *
 * Input schema excerpt:
 * ```php
 * [
 *     'name' => 'Customers',
 *     'shortName' => 'customers',
 *     'operations' => ['Get' => [...], 'Post' => [...]],
 *     'properties' => ['email' => ['type' => 'string', 'required' => true, ...]],
 *     'validation' => ['post' => ['email' => [['NotBlank' => [...]]]]],
 * ]
 * ```
 *
 * Generated output structure:
 * ```php
 * namespace Generated\Api\Storefront;
 * use ApiPlatform\Metadata\ApiResource;
 * // ... more imports
 *
 * #[ApiResource(operations: [new Get(), new Post()], ...)]
 * final class CustomersStorefrontResource
 * {
 *     #[ApiProperty(description: '...')]
 *     #[Assert\NotBlank(groups: ['customers:create'])]
 *     public ?string $email = null;
 *
 *     // ... getters/setters, toArray(), fromArray()
 * }
 * ```
 *
 * Orchestrates property transformation, attribute generation, and code rendering via template system.
 */
class ClassGenerator
{
    protected const string GENERATED_NAMESPACE_PREFIX = 'Generated\Api';

    /**
     * @var array<string>
     */
    protected const array COMPOSITE_CONSTRAINTS_WITH_CONSTRAINTS_PARAMETER = [
        'All',
        'Sequentially',
        'Composite',
    ];

    /**
     * @var array<string, string>
     */
    protected const array TYPE_MAPPING = [
        'string' => 'string',
        'integer' => 'int',
        'number' => 'float',
        'boolean' => 'bool',
        'array' => 'array',
        'object' => 'object',
        'map' => 'array',
        'mixed' => 'mixed',
    ];

    /**
     * @var array<string, array{fqcn: string, shortName: string, alias: string}>
     */
    protected array $fqcnConstraintMap = [];

    /**
     * Companion value-object classes generated for `type: object` properties (e.g. cart `totals`),
     * keyed by class name. They carry no #[ApiResource] attribute and are written as sibling files
     * next to the resource. Reset at the start of every {@see generateAll()} call.
     *
     * @var array<string, string>
     */
    protected array $nestedObjectClasses = [];

    /**
     * Set of canonical object class names (map value always true) for the current {@see generateAll()}
     * call. A property carrying `objectName` is typed to its canonical class and skips per-resource
     * companion generation, because the canonical class is generated once by the registry pre-pass.
     *
     * @var array<string, true>
     */
    protected array $knownCanonicalObjectNames = [];

    /**
     * Short class names of single-object nested properties directly on the resource (not
     * collections). These helpers are relocated into a per-resource sub-namespace by Task 1, so the
     * resource class must `use` them by FQCN. Collection-typed properties resolve to `array` on the
     * resource and are never referenced by short name, so they are excluded. Reset at the start of
     * every {@see generateAll()} call.
     *
     * @var array<string>
     */
    protected array $resourceReferencedHelperClassNames = [];

    /**
     * Short class names of canonical object classes referenced by properties on this resource.
     * Canonical objects live at the shared `Generated\Api\{ApiType}\{Name}` namespace (no
     * resource sub-segment), so they need their own import loop distinct from the per-resource
     * helper loop. Reset at the start of every {@see generateAll()} call.
     *
     * @var array<string>
     */
    protected array $referencedCanonicalObjectNames = [];

    public function __construct(
        protected readonly PhpTemplateRenderer $templateRenderer,
        protected readonly ValidationGroupMapperInterface $validationGroupMapper,
        protected readonly PropertyAttributeGenerator $propertyAttributeGenerator,
        protected readonly ConstraintFormatter $constraintFormatter,
        protected readonly FqcnConstraintResolver $fqcnConstraintResolver,
        protected readonly ValidationAttributeGenerator $validationAttributeGenerator,
        protected readonly ResourceAttributeGenerator $resourceAttributeGenerator,
        protected readonly UseStatementCollector $useStatementCollector,
        protected readonly RelationshipPhpDocGenerator $relationshipPhpDocGenerator,
        protected readonly NestedObjectClassGenerator $nestedObjectClassGenerator,
        protected readonly NestedObjectValidationLifter $nestedObjectValidationLifter,
    ) {
    }

    /**
     * Generates a PHP resource class from a schema, returning only the main class code.
     *
     * Backwards-compatible thin wrapper around {@see generateAll()}.
     *
     * @param array<string, mixed> $schema The resource schema definition
     * @param string $apiType The API type (normalized to ucfirst automatically)
     * @param array<string, true> $knownCanonicalObjectNames Set of canonical object class names (the
     *     map value is always true) produced by {@see \Spryker\ApiPlatform\Generator\CanonicalObjectRegistry}.
     */
    public function generate(array $schema, string $apiType, array $knownCanonicalObjectNames = []): string
    {
        return $this->generateAll($schema, $apiType, $knownCanonicalObjectNames)->getMainClassCode();
    }

    /**
     * Generates a PHP resource class from a schema along with any companion value-object
     * classes generated for typed nested object properties (a `type: object` property with
     * its own `properties`).
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
     * @param array<string, true> $knownCanonicalObjectNames Set of canonical object class names (the
     *     map value is always true) produced by {@see \Spryker\ApiPlatform\Generator\CanonicalObjectRegistry}.
     *     A property carrying `objectName` is typed to the canonical class and never emits a
     *     per-resource companion class; the canonical class is generated once by the registry.
     */
    public function generateAll(array $schema, string $apiType, array $knownCanonicalObjectNames = []): GeneratedResourceResult
    {
        $this->fqcnConstraintMap = [];
        $this->nestedObjectClasses = [];
        $this->resourceReferencedHelperClassNames = [];
        $this->referencedCanonicalObjectNames = [];
        $this->knownCanonicalObjectNames = $knownCanonicalObjectNames;

        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

        $resourceName = $schema['name'];
        $validationResourceName = $schema['shortName'] ?? $schema['name'];
        $codeBucket = $schema['codeBucket'] ?? null;
        $className = $this->generateClassName($resourceName, $apiType, $codeBucket);
        $resourceClassBaseName = ResourceNameNormalizer::normalize((string)$resourceName) . ($codeBucket ?? '');
        $namespace = $this->generateNamespace($apiType);

        $this->fqcnConstraintMap = $this->fqcnConstraintResolver->collectFqcnConstraints(
            $schema['validation'] ?? [],
            $schema['operations'] ?? [],
            $schema['properties'] ?? [],
        );

        $this->constraintFormatter->setFqcnConstraintMap($this->fqcnConstraintMap);

        // Resolve source files before transforming properties: the companion classes generated
        // for typed nested objects reuse them for their own generated-file provenance header.
        $sourceFiles = $this->extractSourceFiles($schema);

        $properties = $this->transformProperties(
            $schema['properties'] ?? [],
            $schema['validation'] ?? [],
            $schema['operations'] ?? [],
            $validationResourceName,
            $schema['includes'] ?? [],
            $apiType,
            $sourceFiles,
            $resourceClassBaseName,
        );
        $uses = $this->useStatementCollector->collect($schema, $properties, $this->fqcnConstraintMap);

        // @phpstan-ignore foreach.emptyArray ($resourceReferencedHelperClassNames is populated by transformProperties() via side-effectful resolveNestedObjectType())
        foreach ($this->resourceReferencedHelperClassNames as $helperShortName) {
            $uses[] = sprintf('%s\\%s\\%s\\%s', static::GENERATED_NAMESPACE_PREFIX, $apiType, $resourceClassBaseName, $helperShortName);
        }

        // $referencedCanonicalObjectNames is populated by transformProperties() via side-effectful resolveNestedObjectType().
        foreach (array_unique($this->referencedCanonicalObjectNames) as $canonicalShortName) {
            $uses[] = sprintf('%s\\%s\\%s', static::GENERATED_NAMESPACE_PREFIX, $apiType, $canonicalShortName);
        }

        $uses = array_values(array_unique($uses));

        $resourceAttribute = $this->resourceAttributeGenerator->generate($schema, $uses);

        $templateData = [
            'className' => $className,
            'namespace' => $namespace,
            'uses' => $uses,
            'resourceAttribute' => $resourceAttribute,
            'properties' => $properties,
            'codeBucket' => $codeBucket,
            'metadata' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'sourceFiles' => $sourceFiles,
                'validationSourceFiles' => $schema['validationSourceFiles'] ?? [],
            ],
        ];

        return new GeneratedResourceResult(
            $this->templateRenderer->render($templateData),
            $this->nestedObjectClasses,
        );
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

    protected function generateClassName(string $resourceName, string $apiType, ?string $codeBucket = null): string
    {
        $resourceName = ResourceNameNormalizer::normalize($resourceName);

        if ($codeBucket !== null) {
            return sprintf('%s%s%sResource', $resourceName, $codeBucket, $apiType);
        }

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
     * @param array<array{relationshipName: string, targetResource: string}> $includes
     * @param array<string> $sourceFiles
     *
     * @return array<array{name: string, type: string, phpType: string, attributes: string, description: string, phpDoc: string, default: mixed, hasDefault: bool, serializedName: string|null, serializedPath: string|null, nullable: bool}>
     */
    protected function transformProperties(
        array $properties,
        array $validationSchema,
        array $operations,
        string $resourceName,
        array $includes = [],
        string $apiType = '',
        array $sourceFiles = [],
        string $resourceClassBaseName = '',
    ): array {
        $transformed = [];

        foreach ($properties as $name => $property) {
            $type = (string)($property['type'] ?? 'string');
            $phpType = $this->resolveNestedObjectOrScalarType(
                (string)$name,
                $property,
                $resourceClassBaseName,
                $resourceName,
                $validationSchema,
                $operations,
                $apiType,
                $sourceFiles,
            );

            $attributes = $this->generatePropertyAttributes($property, $validationSchema, $operations, $name, $resourceName);

            $phpDoc = $this->relationshipPhpDocGenerator->generate(
                $property,
                $name,
                $includes,
                $apiType,
            );

            $transformed[] = [
                'name' => $name,
                'type' => $type,
                'phpType' => $phpType,
                'attributes' => $attributes,
                'description' => (string)($property['description'] ?? ''),
                'phpDoc' => $phpDoc,
                'default' => $property['default'] ?? null,
                'hasDefault' => array_key_exists('default', $property),
                'serializedName' => isset($property['serializedName']) ? (string)$property['serializedName'] : null,
                'serializedPath' => isset($property['serializedPath']) ? (string)$property['serializedPath'] : null,
                'nullable' => !empty($property['nullable']),
            ];
        }

        return $transformed;
    }

    /**
     * Resolves the PHP type for a single property.
     *
     * A typed nested object (`type: object` carrying its own `properties`) or a collection of such
     * objects (`type: array` whose `items` are a typed object) becomes a dedicated per-resource
     * companion value-object class named `{ResourceName}{Field}{ApiType}Resource` (collections
     * pluralize the field segment). This lifts the property's `Collection` field validation onto the
     * companion, generates that class (and any descendants) as a side effect into
     * {@see $nestedObjectClasses}, and returns its class name (or `array` for a collection). Every
     * other property maps straight to its scalar/array PHP type. Kept as guarded early returns so the
     * caller loop stays free of nested branching.
     *
     * @param array<string, mixed> $property
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     * @param array<string> $sourceFiles
     */
    protected function resolveNestedObjectOrScalarType(
        string $name,
        array $property,
        string $resourceClassBaseName,
        string $resourceName,
        array $validationSchema,
        array $operations,
        string $apiType,
        array $sourceFiles
    ): string {
        // Shared canonical object: type to the canonical class without emitting a companion class.
        // The registry generates the canonical class from its `*.object.yml` definition; here we only
        // consume the resolved class name. A property carrying a known `objectName` is a reference
        // site (with or without inline `properties`) and types to that shared class.
        $objectName = $property['objectName'] ?? null;
        if (is_string($objectName) && isset($this->knownCanonicalObjectNames[$objectName])) {
            $this->referencedCanonicalObjectNames[] = $objectName;

            return $objectName;
        }

        if (!$this->isGeneratedNestedObjectProperty($property)) {
            return $this->mapToPhpType($property['type'] ?? 'string');
        }

        $isCollection = ($property['type'] ?? null) === 'array';
        $nestedProperties = $isCollection ? $property['items']['properties'] : $property['properties'];

        // Lift the parent property's Collection field validation onto the value object's fields, so
        // a denormalized object is validated field-by-field (the parent only cascades via Assert\Valid).
        $nestedProperties = $this->nestedObjectValidationLifter->lift(
            $nestedProperties,
            $validationSchema,
            $operations,
            $name,
            $resourceName,
        );

        $childBaseName = $this->nestedObjectClassGenerator->childBaseName($resourceClassBaseName, $name, $property);

        // Union (+=), not array_merge: companion classes are keyed by class name, so the nested
        // object and every descendant it emits are de-duplicated instead of appended twice.
        $this->nestedObjectClasses += $this->nestedObjectClassGenerator->generate(
            $childBaseName,
            $nestedProperties,
            $apiType,
            $sourceFiles,
            (bool)($property['synthesizeMissingFieldsWhenEmpty'] ?? false),
            $resourceClassBaseName,
        );

        if ($isCollection) {
            return 'array';
        }

        $helperShortName = $this->nestedObjectClassGenerator->buildClassName($childBaseName, $apiType);
        $this->resourceReferencedHelperClassNames[] = $helperShortName;

        return $helperShortName;
    }

    /**
     * A property that generates a per-resource companion value-object class: a `type: object` with
     * its own `properties`, or a `type: array` whose `items` are such an object.
     *
     * @param array<string, mixed> $property
     */
    protected function isGeneratedNestedObjectProperty(array $property): bool
    {
        $type = $property['type'] ?? null;

        if ($type === 'object') {
            return isset($property['properties']) && is_array($property['properties']);
        }

        if ($type === 'array' && isset($property['items']) && is_array($property['items']) && ($property['items']['type'] ?? null) === 'object') {
            return isset($property['items']['properties']) && is_array($property['items']['properties']);
        }

        return false;
    }

    /**
     * A property is a known canonical reference site when it carries a non-empty `objectName` that the
     * registry pre-pass resolved into the known-canonical set. Unlike {@see isGeneratedNestedObjectProperty()}
     * this does NOT require inline `properties`: a reference-only site (`objectName`, no `properties`) is
     * typed to the shared canonical class and its Collection validation cascades via Assert\Valid.
     *
     * @param array<string, mixed> $property
     */
    protected function isKnownCanonicalProperty(array $property): bool
    {
        $objectName = $property['objectName'] ?? null;

        return is_string($objectName) && isset($this->knownCanonicalObjectNames[$objectName]);
    }

    protected function mapToPhpType(string $type): string
    {
        if (isset(static::TYPE_MAPPING[$type])) {
            return static::TYPE_MAPPING[$type];
        }

        return $type;
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

        $apiPropertyAttribute = $this->propertyAttributeGenerator->generate(
            $property,
            $validationSchema,
            $operations,
            $propertyName,
            $resourceName,
        );

        if ($apiPropertyAttribute !== '') {
            $attributes[] = $apiPropertyAttribute;
        }

        $validationAttributes = $this->validationAttributeGenerator->generate($validationSchema, $operations, $propertyName, $resourceName);

        // A property that denormalizes into an object — a generated nested-object companion, or a known
        // canonical reference site typed to the shared class — would be rejected by its array-shaped
        // `Collection` constraint. The field-level validation lives on the (companion/canonical) class;
        // the parent property only cascades via Assert\Valid.
        $denormalizesToObject = $this->isGeneratedNestedObjectProperty($property) || $this->isKnownCanonicalProperty($property);
        if ($validationAttributes !== [] && $denormalizesToObject && $this->containsCollectionConstraint($validationAttributes)) {
            $validationAttributes = $this->buildValidCascadeAttribute($validationSchema, $operations, $propertyName, $resourceName);
        }

        if ($validationAttributes !== []) {
            $attributes = array_merge($attributes, $validationAttributes);
        }

        return implode("\n    ", $attributes);
    }

    /**
     * @param array<string> $validationAttributes
     */
    protected function containsCollectionConstraint(array $validationAttributes): bool
    {
        foreach ($validationAttributes as $attribute) {
            if (str_contains($attribute, 'Assert\\Collection(')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the `#[Assert\Valid(groups: [...])]` cascade for a canonical object property, scoped to
     * the same operation groups the replaced Collection constraint carried, so the value object's
     * field validation runs under the active operation group.
     *
     * @param array<string, mixed> $validationSchema
     * @param array<string, mixed> $operations
     *
     * @return array<string>
     */
    protected function buildValidCascadeAttribute(array $validationSchema, array $operations, string $propertyName, string $resourceName): array
    {
        $groups = [];

        foreach ($operations as $operationType => $operation) {
            $httpMethod = strtolower($operation['type'] ?? $operationType);

            if (!isset($validationSchema[$httpMethod][$propertyName])) {
                continue;
            }

            $groups[] = $this->validationGroupMapper->mapOperationToGroup($operation['type'] ?? $operationType, $resourceName);
        }

        $groups = array_values(array_unique($groups));
        sort($groups);

        if ($groups === []) {
            return ['#[Assert\\Valid]'];
        }

        return [sprintf("#[Assert\\Valid(groups: ['%s'])]", implode("', '", $groups))];
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
     * @param array<string, array{fqcn: string, shortName: string, alias: string, namespaceParts: array<string>}> $fqcnConstraints
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
            if (isset($fqcnConstraints[$vendorFqcns[0]])) {
                $fqcnConstraints[$vendorFqcns[0]]['alias'] = sprintf('%s%s', $vendor, $shortName);
            }

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
            if (!isset($fqcnConstraints[$fqcn]['namespaceParts'])) {
                continue;
            }

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
