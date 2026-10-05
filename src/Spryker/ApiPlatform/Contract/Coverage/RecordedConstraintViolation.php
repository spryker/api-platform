<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * One violation the validator raised for a request, in the form a
 * {@see \Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation} names it: the normalized
 * attribute path and the rule identifier. A null rule means the violation reached the wire without
 * a constraint behind it, so only its attribute is known. The property path is the validator's own,
 * kept because only it tells a map key or list index apart from a field
 * ({@see ValidationAttributePath::matches()}).
 */
readonly class RecordedConstraintViolation
{
    public function __construct(
        public string $attributePath,
        public ?string $rule,
        public ?string $propertyPath = null,
    ) {
    }
}
