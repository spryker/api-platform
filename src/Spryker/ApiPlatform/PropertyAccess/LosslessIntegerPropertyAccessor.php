<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\PropertyAccess;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Spryker\ApiPlatform\Exception\LossyIntegerConversionException;
use Spryker\ApiPlatform\Utility\FractionalNumberDetector;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * Refuses to write a number with a fractional part into a property that only accepts `int`.
 *
 * The Glue applications deserialize with `disable_type_enforcement`, so the serializer hands the
 * JSON value to the property accessor unchecked, and Symfony's PropertyAccessor calls the setter
 * without `strict_types`: PHP coerces `1.5` or `"1.5"` to `1` and only raises a deprecation. The
 * validator then sees the truncated value. This decorator makes that conversion fail the way a
 * non-numeric string already does, which is what PHP itself will do once the deprecation turns into
 * an error.
 *
 * Every lossless form keeps its current behaviour: `2`, `2.0`, `"2"`, `"2.0"` and `"1e3"` are
 * written as before. Values the accessor already rejects, such as `"abc"`, are left to it.
 */
class LosslessIntegerPropertyAccessor implements PropertyAccessorInterface
{
    protected const string SETTER_PREFIX = 'set';

    protected const string TYPE_INT = 'int';

    protected const string TYPE_NULL = 'null';

    protected const string PROPERTY_PATH_SEPARATORS = '.[';

    public function __construct(protected readonly PropertyAccessorInterface $decorated)
    {
    }

    /**
     * @param-out object|array<mixed> $objectOrArray
     *
     * @param object|array<mixed> $objectOrArray
     *
     * @throws \Spryker\ApiPlatform\Exception\LossyIntegerConversionException
     */
    public function setValue(object|array &$objectOrArray, string|PropertyPathInterface $propertyPath, mixed $value): void
    {
        if (is_object($objectOrArray) && $this->isLossyIntegerWrite($objectOrArray, (string)$propertyPath, $value)) {
            throw new LossyIntegerConversionException((string)$propertyPath);
        }

        $this->decorated->setValue($objectOrArray, $propertyPath, $value);
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function getValue(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): mixed
    {
        return $this->decorated->getValue($objectOrArray, $propertyPath);
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function isWritable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isWritable($objectOrArray, $propertyPath);
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function isReadable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isReadable($objectOrArray, $propertyPath);
    }

    protected function isLossyIntegerWrite(object $object, string $propertyPath, mixed $value): bool
    {
        if (!FractionalNumberDetector::isFractional($value) || strpbrk($propertyPath, static::PROPERTY_PATH_SEPARATORS) !== false) {
            return false;
        }

        $type = $this->findWriteType($object, $propertyPath);

        return $type !== null && $this->acceptsOnlyInteger($type);
    }

    /**
     * Mirrors the order the property accessor writes in: the setter first, then the property.
     */
    protected function findWriteType(object $object, string $propertyName): ?ReflectionType
    {
        $setterName = static::SETTER_PREFIX . $this->camelize($propertyName);

        if (method_exists($object, $setterName)) {
            $setter = new ReflectionMethod($object, $setterName);
            $parameters = $setter->getParameters();

            return $setter->isPublic() && $parameters !== [] ? $parameters[0]->getType() : null;
        }

        if (property_exists($object, $propertyName)) {
            return (new ReflectionProperty($object, $propertyName))->getType();
        }

        return null;
    }

    /**
     * True for `int` and `?int` only. A union that also accepts `float`, `string`, `bool` or `mixed`
     * does not truncate, so it is left alone.
     */
    protected function acceptsOnlyInteger(ReflectionType $type): bool
    {
        $memberTypes = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        $typeNames = [];

        foreach ($memberTypes as $memberType) {
            if (!$memberType instanceof ReflectionNamedType) {
                return false;
            }

            $typeNames[] = $memberType->getName();
        }

        return array_values(array_diff($typeNames, [static::TYPE_NULL])) === [static::TYPE_INT];
    }

    protected function camelize(string $propertyName): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $propertyName)));
    }
}
