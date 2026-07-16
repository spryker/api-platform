<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Metadata\Property;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\TypeInfo\Type;

/**
 * Teaches API Platform the PHP type of a generated nested value-object property (e.g. a cart
 * `totals` typed `?CartsTotalsStorefrontObject`, or a writable checkout `customer` typed
 * `?CheckoutCustomerStorefrontObject`).
 *
 * API Platform only recurses into a nested object during denormalization when the property
 * metadata's native type resolves to that class (see
 * {@see \ApiPlatform\Serializer\AbstractItemNormalizer::createAndValidateAttributeValue()} —
 * the object branch delegates to `serializer->denormalize($value, $className)`). For these
 * generated resources the native type is otherwise not surfaced into the metadata, so a
 * write-only property (e.g. `payment`) receives the raw request array and the typed setter
 * throws a `TypeError`. Read-only nested objects are unaffected either way because they are
 * never denormalized — but setting the native type for them is harmless and keeps the schema
 * consistent.
 *
 * The native type is set with {@see ApiProperty::withNativeType()}; API Platform's own
 * (legacy) `getBuiltinTypes()` reads it back through its TypeInfo conversion, so the object
 * denormalization branch fires and the value object is hydrated via the serializer.
 */
class NestedObjectPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    /**
     * Generated resource AND value-object classes share this namespace prefix; only value
     * objects (no #[ApiResource]) are augmented here — resource relations stay untouched so
     * API Platform keeps handling them as IRIs/embedded resources.
     */
    protected const string GENERATED_API_NAMESPACE_PREFIX = 'Generated\\Api\\';

    /**
     * Generated value objects expose this static factory; used as the marker that a class is a
     * plain nested value object rather than some other collaborator referenced by a property.
     */
    protected const string VALUE_OBJECT_FACTORY_METHOD = 'fromArray';

    public function __construct(
        protected readonly PropertyMetadataFactoryInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        $valueObjectClass = $this->resolveNestedValueObjectClass($resourceClass, $property);

        if ($valueObjectClass === null) {
            return $propertyMetadata;
        }

        return $propertyMetadata->withNativeType(Type::nullable(Type::object($valueObjectClass)));
    }

    /**
     * Returns the generated value-object class a property is typed to, or null when the property
     * is not a generated nested value object (scalar, array, resource relation, or unresolved).
     */
    protected function resolveNestedValueObjectClass(string $resourceClass, string $property): ?string
    {
        if (!class_exists($resourceClass) || !property_exists($resourceClass, $property)) {
            return null;
        }

        $propertyType = (new ReflectionProperty($resourceClass, $property))->getType();

        if (!$propertyType instanceof ReflectionNamedType || $propertyType->isBuiltin()) {
            return null;
        }

        $className = $propertyType->getName();

        if (!str_starts_with($className, static::GENERATED_API_NAMESPACE_PREFIX) || !class_exists($className)) {
            return null;
        }

        // A generated nested value object carries fromArray() and no #[ApiResource] — anything
        // else (notably a resource relation) must keep API Platform's default handling.
        if (!method_exists($className, static::VALUE_OBJECT_FACTORY_METHOD)) {
            return null;
        }

        if ((new ReflectionClass($className))->getAttributes(ApiResource::class) !== []) {
            return null;
        }

        return $className;
    }
}
