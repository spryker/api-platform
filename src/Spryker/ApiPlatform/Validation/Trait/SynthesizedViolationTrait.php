<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation\Trait;

use ReflectionClass;
use Spryker\ApiPlatform\Validation\SynthesizedViolation;
use Symfony\Component\Validator\Constraint;

/**
 * Attaches to a synthesized validation error the constraints it stands for.
 *
 * @see \Spryker\ApiPlatform\Validation\SynthesizedViolation
 */
trait SynthesizedViolationTrait
{
    /**
     * @param array<string, mixed> $error
     * @param array<string> $constraintShortNames
     *
     * @return array<string, mixed>
     */
    protected function withSynthesizedViolations(array $error, string $propertyPath, array $constraintShortNames): array
    {
        foreach (array_unique($constraintShortNames) as $constraintShortName) {
            $error[SynthesizedViolation::ERROR_KEY][] = new SynthesizedViolation($propertyPath, $constraintShortName);
        }

        return $error;
    }

    protected function constraintShortName(Constraint $constraint): string
    {
        return (new ReflectionClass($constraint))->getShortName();
    }
}
