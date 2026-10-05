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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;

/**
 * Derives the attributes a client can write on each input operation of a generated resource: every
 * writable property, one level deep - the writable children of a nested value object, the fields of
 * an `Assert\Collection` (optional ones included), and the fields of a list of such collections
 * under the `[]` wildcard. A deeper collection counts through its first-level field.
 *
 * A property declared `writableOn` is demanded only of the operation types it lists.
 */
class RequestAttributeTruthCollector
{
    /**
     * @uses \Spryker\ApiPlatform\Generator\PropertyAttributeGenerator::EXTRA_PROPERTY_WRITABLE_ON
     */
    protected const string EXTRA_PROPERTY_WRITABLE_ON = 'writableOn';

    protected const string RELATIONSHIP_DATA_SUFFIX = 'RelationshipData';

    protected const string DEFAULT_IDENTIFIER = 'id';

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
     * @param class-string $class
     */
    protected function isGeneratedNestedObjectClass(string $class): bool
    {
        return str_starts_with($class, static::GENERATED_API_NAMESPACE_PREFIX)
            && (new ReflectionClass($class))->getAttributes(ApiResource::class) === [];
    }

    /**
     * @param \ReflectionClass<object> $resourceClass
     * @param array<array{operation: \Spryker\ApiPlatform\Contract\Coverage\ApiOperation, type: string, ...}> $inputOperations
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\RequestAttribute>
     */
    public function collect(ReflectionClass $resourceClass, array $inputOperations): array
    {
        $requestAttributes = [];

        foreach ($inputOperations as $inputOperation) {
            foreach ($this->collectPaths($resourceClass, '', $inputOperation['type'], descend: true) as $path) {
                $requestAttributes[] = new RequestAttribute($inputOperation['operation']->dispatchKey(), $path);
            }
        }

        return $requestAttributes;
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return array<string>
     */
    protected function collectPaths(ReflectionClass $class, string $prefix, string $operationType, bool $descend): array
    {
        $paths = [];

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $apiProperty = $this->apiProperty($property);
            if ($this->isSkipped($property, $apiProperty, $operationType, isResourceLevel: $prefix === '')) {
                continue;
            }

            $name = $prefix . $property->getName();
            $nestedClass = $this->nestedObjectClass($property);
            if ($nestedClass !== null) {
                $paths = $descend
                    ? array_merge($paths, $this->collectPaths($nestedClass, $name . static::PATH_SEPARATOR, $operationType, descend: false))
                    : [...$paths, $name];

                continue;
            }

            $collectionFields = $descend ? $this->collectionFieldPaths($property, $name) : [];
            $paths = $collectionFields !== [] ? array_merge($paths, $collectionFields) : [...$paths, $name];
        }

        return $paths;
    }

    /**
     * @return array<string>
     */
    protected function collectionFieldPaths(ReflectionProperty $property, string $name): array
    {
        foreach ($property->getAttributes() as $attribute) {
            if (!is_subclass_of($attribute->getName(), Constraint::class)) {
                continue;
            }

            $constraint = $attribute->newInstance();

            if ($constraint instanceof Collection) {
                return $this->fieldPaths($constraint, $name . static::PATH_SEPARATOR);
            }

            if ($constraint instanceof All) {
                foreach ($this->toConstraintList($constraint->constraints) as $nestedConstraint) {
                    if ($nestedConstraint instanceof Collection) {
                        return $this->fieldPaths($nestedConstraint, $name . static::PATH_WILDCARD . static::PATH_SEPARATOR);
                    }
                }
            }
        }

        return [];
    }

    /**
     * @return array<string>
     */
    protected function fieldPaths(Collection $collection, string $prefix): array
    {
        return array_map(
            static fn (int|string $field): string => $prefix . $field,
            array_keys($collection->fields),
        );
    }

    /**
     * @return array<\Symfony\Component\Validator\Constraint>
     */
    protected function toConstraintList(mixed $constraints): array
    {
        if ($constraints instanceof Constraint) {
            return [$constraints];
        }

        return is_array($constraints) ? array_values(array_filter($constraints, static fn (mixed $constraint): bool => $constraint instanceof Constraint)) : [];
    }

    protected function apiProperty(ReflectionProperty $property): ?ApiProperty
    {
        $attributes = $property->getAttributes(ApiProperty::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * An undeclared `id` is the resource's identifier, but a nested value object's `id` is data.
     */
    protected function isSkipped(ReflectionProperty $property, ?ApiProperty $apiProperty, string $operationType, bool $isResourceLevel): bool
    {
        if (str_ends_with($property->getName(), static::RELATIONSHIP_DATA_SUFFIX) || $this->isResourceTyped($property)) {
            return true;
        }

        if ($apiProperty === null) {
            return $isResourceLevel && $property->getName() === static::DEFAULT_IDENTIFIER;
        }

        if ($apiProperty->isWritable() === false || $apiProperty->isIdentifier() === true || $apiProperty->getUriTemplate() !== null) {
            return true;
        }

        $writableOn = $apiProperty->getExtraProperties()[static::EXTRA_PROPERTY_WRITABLE_ON] ?? null;

        return is_array($writableOn) && !in_array($operationType, $writableOn, true);
    }

    /**
     * A property typed as another resource is a relationship, which a request never writes.
     */
    protected function isResourceTyped(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && class_exists($type->getName())
            && (new ReflectionClass($type->getName()))->getAttributes(ApiResource::class) !== [];
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
}
