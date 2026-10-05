<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Validation;

/**
 * The constraint a validation error stands for when the error was synthesized outside the
 * validator, by one of the validation error augmenters. The augmenter attaches it to the error it
 * builds under {@see SynthesizedViolation::ERROR_KEY}; the exception subscriber strips it before the
 * response is written and keeps it on the request under
 * {@see \Spryker\ApiPlatform\Request\RequestAttribute::SYNTHESIZED_VIOLATIONS}.
 *
 * The error detail cannot carry this: it names neither the rule nor, for a nested leaf, the full
 * path. The contract coverage reads it to prove a validation declaration by rule and full path.
 */
readonly class SynthesizedViolation
{
    public const string ERROR_KEY = '_synthesizedViolations';

    /**
     * @param string $propertyPath The full dot path from the resource attributes, e.g. `billingAddress.zipCode`.
     * @param string $constraintShortName The constraint class short name, e.g. `NotBlank`.
     */
    public function __construct(
        public string $propertyPath,
        public string $constraintShortName,
    ) {
    }
}
