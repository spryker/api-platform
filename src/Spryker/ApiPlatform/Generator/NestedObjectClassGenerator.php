<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Spryker\ApiPlatform\Generator\Result\ResolvedPropertyType;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Spryker\ApiPlatform\Utility\ResourceNameInflector;

/**
 * Generates plain typed value-object classes for `type: object` properties that declare nested
 * `properties` (and for `type: array` properties whose `items` are such objects). Recurses for
 * objects within objects. The generated classes carry no #[ApiResource] attribute — they are
 * embedded objects whose typed properties drive the OpenAPI component schema and are hydrated by
 * the serializer.
 *
 * Each class is named per resource and field: the caller passes the suffix-less base name
 * (`{ResourceName}{Field}`) and the renderer appends `{ApiType}Object`, so a cart resource's
 * `customer` property generates `CartsCustomerStorefrontObject`. An object collection pluralizes
 * the field segment (`CartsCustomersStorefrontObject`). When `$ownerResourceName` is provided, the
 * class lands in the per-owner sub-namespace `Generated\Api\{ApiType}\{OwnerResourceName}`.
 */
class NestedObjectClassGenerator
{
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

    protected const string GENERATED_NAMESPACE_PREFIX = 'Generated\\Api';

    protected const string OBJECT_CLASS_SUFFIX = 'Object';

    protected const string FQCN_API_PROPERTY = 'ApiPlatform\\Metadata\\ApiProperty';

    protected const string FQCN_SERIALIZED_NAME = 'Symfony\\Component\\Serializer\\Attribute\\SerializedName';

    protected const string FQCN_SERIALIZED_PATH = 'Symfony\\Component\\Serializer\\Attribute\\SerializedPath';

    protected const string FQCN_VALIDATOR_CONSTRAINTS = 'Symfony\\Component\\Validator\\Constraints as Assert';

    public function __construct(
        protected readonly PropertyAttributeGenerator $propertyAttributeGenerator,
        protected readonly PhpTemplateRenderer $templateRenderer,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<string> $sourceFiles
     * @param string|null $classNameOverride When set, the top-level class is emitted under this exact
     *     name instead of the suffixed `{Base}{ApiType}Object` form. Used for shared canonical objects,
     *     whose class name must be the bare `objectName` every referencing resource imports and types
     *     (e.g. `Address`, not `AddressStorefrontObject`). Descendant classes keep the suffixed naming.
     *
     * @return array<string, string> Keyed by class name → generated PHP code. Includes this class and all descendants.
     */
    public function generate(
        string $baseName,
        array $properties,
        string $apiType,
        array $sourceFiles,
        bool $synthesizeMissingFieldsWhenEmpty = false,
        string $ownerResourceName = '',
        ?string $classNameOverride = null,
    ): array {
        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);
        $className = $classNameOverride ?? $this->buildClassName($baseName, $apiType);

        $namespace = $ownerResourceName !== ''
            ? sprintf('%s\\%s\\%s', static::GENERATED_NAMESPACE_PREFIX, $apiType, $ownerResourceName)
            : sprintf('%s\\%s', static::GENERATED_NAMESPACE_PREFIX, $apiType);

        $classes = [];
        $templateProperties = [];
        $uses = [];

        foreach ($properties as $name => $property) {
            $name = (string)$name;
            $type = $property['type'] ?? 'string';

            // A nested object (or a collection of them) becomes its own child class (recursively);
            // its descendants are merged into $classes here so the caller writes one file per class.
            $classes += $this->generateChildClasses($baseName, $name, $property, $apiType, $sourceFiles, $ownerResourceName);
            $resolvedType = $this->resolvePropertyType($baseName, $name, $property, $apiType, $ownerResourceName);

            $attributes = $this->propertyAttributeGenerator->generate($property, [], [], $name, $className);

            // Field-level validation moved off the parent resource property (which now carries
            // only `#[Assert\Valid]`) and onto the value object, so a denormalized object is
            // validated field-by-field instead of failing an array-shaped Collection constraint.
            $validationAttributes = (isset($property['_validationAttributes']) && is_array($property['_validationAttributes']))
                ? array_values(array_filter($property['_validationAttributes'], 'is_string'))
                : [];

            if ($validationAttributes !== []) {
                $attributes = $attributes === ''
                    ? implode("\n    ", $validationAttributes)
                    : $attributes . "\n    " . implode("\n    ", $validationAttributes);
                $uses[static::FQCN_VALIDATOR_CONSTRAINTS] = static::FQCN_VALIDATOR_CONSTRAINTS;
            }

            $serializedName = $property['serializedName'] ?? null;
            $serializedPath = $property['serializedPath'] ?? null;

            // Imports are emitted only when actually referenced. An unused `use` for a class that
            // does not exist under the Generated namespace would otherwise make attribute
            // reflection fail at runtime (and trip the unused-import sniff).
            if (str_contains($attributes, 'ApiProperty(')) {
                $uses[static::FQCN_API_PROPERTY] = static::FQCN_API_PROPERTY;
            }

            if ($serializedName !== null && $serializedName !== '') {
                $uses[static::FQCN_SERIALIZED_NAME] = static::FQCN_SERIALIZED_NAME;
            }

            if ($serializedPath !== null && $serializedPath !== '') {
                $uses[static::FQCN_SERIALIZED_PATH] = static::FQCN_SERIALIZED_PATH;
            }

            $templateProperties[] = [
                'name' => $name,
                'type' => $type,
                'phpType' => $resolvedType->phpType,
                'itemClass' => $resolvedType->itemClassFqcn,
                'attributes' => $attributes,
                'description' => $property['description'] ?? '',
                'phpDoc' => $resolvedType->itemClassFqcn !== null
                    ? sprintf('@var array<int, %s>', $resolvedType->itemClassFqcn)
                    : '',
                'hasDefault' => false,
                'serializedName' => $serializedName,
                'serializedPath' => $serializedPath,
                'nullable' => !empty($property['nullable']),
            ];
        }

        $classes[$className] = $this->templateRenderer->render([
            'className' => $className,
            'namespace' => $namespace,
            'uses' => array_values($uses),
            'resourceAttribute' => '',
            'properties' => $templateProperties,
            'codeBucket' => null,
            'synthesizeMissingFieldsWhenEmpty' => $synthesizeMissingFieldsWhenEmpty,
            'metadata' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'sourceFiles' => $sourceFiles,
                'validationSourceFiles' => [],
            ],
        ]);

        return $classes;
    }

    /**
     * Builds the full class name from a suffix-less base: `{Base}{ApiType}Object`.
     */
    public function buildClassName(string $baseName, string $apiType): string
    {
        return sprintf('%s%s%s', $baseName, ApiTypeNormalizer::normalizeForGeneration($apiType), static::OBJECT_CLASS_SUFFIX);
    }

    /**
     * The child base name for a nested property: the parent base plus the field segment. A collection
     * pluralizes the segment (`customer` → `Customers`), a single object capitalizes it.
     *
     * @param array<string, mixed> $property
     */
    public function childBaseName(string $baseName, string $name, array $property): string
    {
        if ($this->isObjectCollection($property)) {
            return $baseName . ResourceNameInflector::pluralizeSegment($name);
        }

        return $baseName . ucfirst($name);
    }

    /**
     * Resolves the PHP type of a single property: a nested object resolves to its generated child
     * class name, a collection of objects to `array` plus the fully qualified element class,
     * everything else to its mapped scalar/array type.
     *
     * @param array<string, mixed> $property
     */
    protected function resolvePropertyType(
        string $baseName,
        string $name,
        array $property,
        string $apiType,
        string $ownerResourceName
    ): ResolvedPropertyType {
        if ($this->isNestedObject($property)) {
            return new ResolvedPropertyType($this->buildClassName($this->childBaseName($baseName, $name, $property), $apiType));
        }

        if ($this->isObjectCollection($property)) {
            $childClassName = $this->buildClassName($this->childBaseName($baseName, $name, $property), $apiType);

            $itemClassFqcn = $ownerResourceName !== ''
                ? sprintf('\\%s\\%s\\%s\\%s', static::GENERATED_NAMESPACE_PREFIX, $apiType, $ownerResourceName, $childClassName)
                : sprintf('\\%s\\%s\\%s', static::GENERATED_NAMESPACE_PREFIX, $apiType, $childClassName);

            return new ResolvedPropertyType('array', $itemClassFqcn);
        }

        $type = $property['type'] ?? 'string';

        return new ResolvedPropertyType(static::TYPE_MAPPING[$type] ?? $type);
    }

    /**
     * Generates the child class (and all its descendants) for a nested object or object-collection
     * property. Returns an empty array for scalar/plain-array properties.
     *
     * @param array<string, mixed> $property
     * @param array<string> $sourceFiles
     *
     * @return array<string, string>
     */
    protected function generateChildClasses(
        string $baseName,
        string $name,
        array $property,
        string $apiType,
        array $sourceFiles,
        string $ownerResourceName = '',
    ): array {
        $childBaseName = $this->childBaseName($baseName, $name, $property);

        if ($this->isNestedObject($property)) {
            return $this->generate($childBaseName, $property['properties'], $apiType, $sourceFiles, false, $ownerResourceName);
        }

        if ($this->isObjectCollection($property)) {
            return $this->generate($childBaseName, $property['items']['properties'], $apiType, $sourceFiles, false, $ownerResourceName);
        }

        return [];
    }

    /**
     * A typed nested object is a `type: object` property that declares its own `properties`.
     *
     * @param array<string, mixed> $property
     */
    protected function isNestedObject(array $property): bool
    {
        return ($property['type'] ?? null) === 'object'
            && isset($property['properties'])
            && is_array($property['properties']);
    }

    /**
     * An object collection is a `type: array` property whose `items` are themselves a typed object.
     *
     * @param array<string, mixed> $property
     */
    protected function isObjectCollection(array $property): bool
    {
        return ($property['type'] ?? null) === 'array'
            && isset($property['items'])
            && is_array($property['items'])
            && $this->isNestedObject($property['items']);
    }
}
