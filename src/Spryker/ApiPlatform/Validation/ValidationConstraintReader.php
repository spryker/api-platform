<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

use ReflectionProperty;
use Symfony\Component\Validator\Constraint;
use Throwable;

/**
 * Reads validation constraints declared as attributes on resource and value-object properties.
 *
 * Shared by the exception subscriber and the nested-object augmenter so constraint reading is not
 * duplicated across both.
 */
class ValidationConstraintReader
{
    /**
     * @return array<\ReflectionAttribute<object>>
     */
    public function getPropertyAttributes(string $resourceClass, string $fieldName): array
    {
        if (!property_exists($resourceClass, $fieldName)) {
            return [];
        }

        /** @phpstan-var class-string $resourceClass */
        return (new ReflectionProperty($resourceClass, $fieldName))->getAttributes();
    }

    /**
     * Returns instantiated constraints for a property, filtered to those that apply to the given
     * validation groups. When $groups is empty all constraints are returned.
     *
     * @param array<string> $groups
     *
     * @return array<\Symfony\Component\Validator\Constraint>
     */
    public function getConstraintsForGroups(string $resourceClass, string $fieldName, array $groups): array
    {
        $constraints = [];

        foreach ($this->getPropertyAttributes($resourceClass, $fieldName) as $attribute) {
            try {
                $constraint = $attribute->newInstance();
            } catch (Throwable) {
                continue;
            }

            if (!$constraint instanceof Constraint) {
                continue;
            }

            if ($groups !== [] && array_intersect($constraint->groups, $groups) === []) {
                continue;
            }

            $constraints[] = $constraint;
        }

        return $constraints;
    }
}
