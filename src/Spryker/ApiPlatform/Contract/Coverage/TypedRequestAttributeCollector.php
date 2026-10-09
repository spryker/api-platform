<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Picks, out of the attributes a client can write on each input operation, the ones whose value
 * has a shape of its own and so needs a constraint: a date, or one of a closed set.
 *
 * An attribute counts as typed when its property is a `DateTimeInterface`, when its OpenAPI context
 * declares an `enum` or a `format`, or when its name is one of the closed sets every Spryker shop
 * has - store, currency, price mode, locale - or reads as a date. Which of them a project actually
 * validates is the project's decision, which `contract_coverage_excluded_unconstrained_attributes`
 * records.
 */
class TypedRequestAttributeCollector
{
    /**
     * @var array<string>
     */
    protected const array ENUM_LIKE_ATTRIBUTE_NAMES = ['store', 'currency', 'priceMode', 'locale'];

    protected const string PATTERN_DATE_LIKE_ATTRIBUTE_NAME = '/^(dateOf[A-Z]\w*|valid(From|To)|\w+(Date|At))$/';

    /**
     * @var array<string>
     */
    protected const array OPENAPI_TYPE_KEYS = ['enum', 'format'];

    /**
     * @var non-empty-string
     */
    protected const string PATH_SEPARATOR = '.';

    protected const string PATH_WILDCARD = '[]';

    public function __construct(protected RequestAttributeTruthCollector $requestAttributeTruthCollector)
    {
    }

    /**
     * @param \ReflectionClass<object> $resourceClass
     * @param array<array{operation: \Spryker\ApiPlatform\Contract\Coverage\ApiOperation, type: string, ...}> $inputOperations
     *
     * @return array<\Spryker\ApiPlatform\Contract\Coverage\TypedRequestAttribute>
     */
    public function collect(ReflectionClass $resourceClass, string $shortName, array $inputOperations): array
    {
        $typedRequestAttributes = [];

        foreach ($this->requestAttributeTruthCollector->collect($resourceClass, $inputOperations) as $requestAttribute) {
            if ($this->isTyped($resourceClass, $requestAttribute->path)) {
                $typedRequestAttributes[] = new TypedRequestAttribute($shortName, $requestAttribute->dispatchKey, $requestAttribute->path);
            }
        }

        return $typedRequestAttributes;
    }

    /**
     * @param \ReflectionClass<object> $resourceClass
     */
    protected function isTyped(ReflectionClass $resourceClass, string $path): bool
    {
        $segments = explode(static::PATH_SEPARATOR, str_replace(static::PATH_WILDCARD, '', $path));
        $attributeName = (string)end($segments);

        if (in_array($attributeName, static::ENUM_LIKE_ATTRIBUTE_NAMES, true) || preg_match(static::PATTERN_DATE_LIKE_ATTRIBUTE_NAME, $attributeName) === 1) {
            return true;
        }

        $property = $this->resolveProperty($resourceClass, $segments);

        return $property !== null && ($this->isDateTyped($property) || $this->declaresOpenApiType($property));
    }

    /**
     * A field of an `Assert\Collection` is an array key rather than a property, so it resolves to
     * nothing and is judged by its name alone.
     *
     * @param \ReflectionClass<object> $class
     * @param array<string> $segments
     */
    protected function resolveProperty(ReflectionClass $class, array $segments): ?ReflectionProperty
    {
        $property = null;

        foreach ($segments as $segment) {
            if ($property !== null) {
                $type = $property->getType();
                if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName())) {
                    return null;
                }
                $class = new ReflectionClass($type->getName());
            }

            if (!$class->hasProperty($segment)) {
                return null;
            }
            $property = $class->getProperty($segment);
        }

        return $property;
    }

    protected function isDateTyped(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && is_a($type->getName(), DateTimeInterface::class, true);
    }

    protected function declaresOpenApiType(ReflectionProperty $property): bool
    {
        $attributes = $property->getAttributes(ApiProperty::class);
        if ($attributes === []) {
            return false;
        }

        $openapiContext = $attributes[0]->newInstance()->getOpenapiContext() ?? [];

        return array_intersect(static::OPENAPI_TYPE_KEYS, array_keys($openapiContext)) !== [];
    }
}
