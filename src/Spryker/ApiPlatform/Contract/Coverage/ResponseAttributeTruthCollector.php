<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Derives the response attributes a test must assert on from a generated `#[ApiResource]` class:
 * every readable, required property of the resource becomes one path, and the same path list is
 * paired with each success operation of the resource.
 *
 * A property is left out when the response need not carry it: `readable: false`, the identifier,
 * a relationship link, or the `responseOptional` opt-out. Nested objects are derived one level
 * deep.
 */
class ResponseAttributeTruthCollector
{
    /**
     * @uses \Spryker\ApiPlatform\Generator\PropertyAttributeGenerator::EXTRA_PROPERTY_RESPONSE_OPTIONAL
     */
    protected const string EXTRA_PROPERTY_RESPONSE_OPTIONAL = 'responseOptional';

    /**
     * @uses \Spryker\ApiPlatform\Generator\PropertyAttributeGenerator::EXTRA_PROPERTY_COLLECTION_ONLY
     */
    protected const string EXTRA_PROPERTY_COLLECTION_ONLY = 'collectionOnly';

    /**
     * @uses \Spryker\ApiPlatform\Generator\PropertyAttributeGenerator::EXTRA_PROPERTY_ITEM_ONLY
     */
    protected const string EXTRA_PROPERTY_ITEM_ONLY = 'itemOnly';

    protected const string RELATIONSHIP_DATA_SUFFIX = 'RelationshipData';

    protected const string DEFAULT_IDENTIFIER = 'id';

    protected const string OPENAPI_KEY_ITEMS = 'items';

    protected const string OPENAPI_KEY_REQUIRED = 'required';

    protected const string PATH_SEPARATOR = '.';

    protected const string PATH_WILDCARD = '[]';

    /**
     * @uses \Spryker\ApiPlatform\Contract\Coverage\SchemaTruthLoader::GENERATED_API_NAMESPACE_PREFIX
     */
    protected const string GENERATED_API_NAMESPACE_PREFIX = 'Generated\\Api\\';

    protected Closure $isNestedObjectClass;

    public function __construct(?callable $isNestedObjectClass = null)
    {
        $this->isNestedObjectClass = $isNestedObjectClass !== null
            ? Closure::fromCallable($isNestedObjectClass)
            : $this->isGeneratedNestedObjectClass(...);
    }

    /**
     * A generated class is a nested value shape unless it is a sibling resource — both live under
     * `Generated\Api\`, and only the resources carry `#[ApiResource]`.
     *
     * @param class-string $class
     */
    protected function isGeneratedNestedObjectClass(string $class): bool
    {
        return str_starts_with($class, static::GENERATED_API_NAMESPACE_PREFIX)
            && (new ReflectionClass($class))->getAttributes(ApiResource::class) === [];
    }

    /**
     * @param \ReflectionClass<object> $resourceClass
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $successOperations
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute>
     */
    public function collect(ReflectionClass $resourceClass, array $successOperations): array
    {
        $collectionPaths = $this->collectPaths($resourceClass, '', descend: true, addressesCollection: true);
        $itemPaths = $this->collectPaths($resourceClass, '', descend: true, addressesCollection: false);
        $responseAttributes = [];

        foreach ($successOperations as $operation) {
            $paths = $operation->addressesCollection ? $collectionPaths : $itemPaths;

            foreach ($paths as $path) {
                $responseAttributes[] = new ResponseAttribute($operation->dispatchKey(), $path);
            }
        }

        return $responseAttributes;
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return array<string>
     */
    protected function collectPaths(ReflectionClass $class, string $prefix, bool $descend, bool $addressesCollection = true): array
    {
        $paths = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $apiProperty = $this->apiProperty($property);
            if ($this->isSkipped($property, $apiProperty)) {
                continue;
            }
            if ($this->isScopedToTheOtherShape($apiProperty, $addressesCollection)) {
                continue;
            }
            $name = $prefix . $property->getName();
            $nestedClass = $this->nestedObjectClass($property);
            if ($nestedClass !== null && $descend) {
                $paths = array_merge($paths, $this->collectPaths($nestedClass, $name . static::PATH_SEPARATOR, descend: false));

                continue;
            }
            $itemFields = $this->requiredItemFields($apiProperty);
            if ($itemFields !== []) {
                foreach ($itemFields as $field) {
                    $paths[] = $name . static::PATH_WILDCARD . static::PATH_SEPARATOR . $field;
                }

                continue;
            }
            $paths[] = $name;
        }

        return $paths;
    }

    protected function apiProperty(ReflectionProperty $property): ?ApiProperty
    {
        $attributes = $property->getAttributes(ApiProperty::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    protected function isSkipped(ReflectionProperty $property, ?ApiProperty $apiProperty): bool
    {
        if (str_ends_with($property->getName(), static::RELATIONSHIP_DATA_SUFFIX)) {
            return true;
        }
        // Deliberately narrower than SchemaTruthLoader::resolveIdentifier(): `id` counts as the
        // identifier only when nothing says otherwise. A resource may mark another property
        // `identifier: true` and keep `id` as an explicit `identifier: false` response field, which
        // a name-based rule would drop from the truth.
        if ($apiProperty === null) {
            return $property->getName() === static::DEFAULT_IDENTIFIER;
        }

        return $apiProperty->isReadable() === false
            || $apiProperty->isIdentifier() === true
            || $apiProperty->getUriTemplate() !== null
            || ($apiProperty->getExtraProperties()[static::EXTRA_PROPERTY_RESPONSE_OPTIONAL] ?? false) === true;
    }

    /**
     * The two halves of the same idea: a resource whose collection and item responses carry
     * different shapes says so in the schema, and the gate demands each property of the shape that
     * has it.
     *
     * `collectionOnly` is the pagination metadata a collection response carries on its first
     * member and an item read can never have. `itemOnly` is its mirror: a list that answers a
     * summary - the orders collection is the reference case, where the legacy contract has the list
     * carry the reference, dates and totals while the full order, its items and its addresses come
     * from the detail read.
     */
    protected function isScopedToTheOtherShape(?ApiProperty $apiProperty, bool $addressesCollection): bool
    {
        if ($apiProperty === null) {
            return false;
        }

        $scopeKey = $addressesCollection ? static::EXTRA_PROPERTY_ITEM_ONLY : static::EXTRA_PROPERTY_COLLECTION_ONLY;

        return ($apiProperty->getExtraProperties()[$scopeKey] ?? false) === true;
    }

    /**
     * @return \ReflectionClass<object>|null
     */
    protected function nestedObjectClass(ReflectionProperty $property): ?ReflectionClass
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName())) {
            return null;
        }

        return ($this->isNestedObjectClass)($type->getName()) ? new ReflectionClass($type->getName()) : null;
    }

    /**
     * @return array<string>
     */
    protected function requiredItemFields(?ApiProperty $apiProperty): array
    {
        $items = $apiProperty?->getOpenapiContext()[static::OPENAPI_KEY_ITEMS] ?? null;
        if (!is_array($items) || !isset($items[static::OPENAPI_KEY_REQUIRED]) || !is_array($items[static::OPENAPI_KEY_REQUIRED])) {
            return [];
        }

        return array_values(array_map(static fn (mixed $field): string => (string)$field, $items[static::OPENAPI_KEY_REQUIRED]));
    }
}
