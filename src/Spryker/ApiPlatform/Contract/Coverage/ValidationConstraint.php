<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The unit of validation coverage: a resource short name, the guarded attribute, the rule
 * identifier ({@see \Spryker\ApiPlatform\Contract\Attribute\Rule}) and the input operation whose
 * validation groups make the rule active — comparable by {@see ValidationConstraint::key()}.
 *
 * The operation is part of the identity: a rule active on two operations (a `NotBlank` enforced on
 * both POST and PATCH) is two entries, and each needs its own test.
 */
readonly class ValidationConstraint
{
    public function __construct(
        public string $resource,
        public string $attribute,
        public string $rule,
        public string $verb,
        public string $uriTemplate,
    ) {
    }

    public function key(): string
    {
        return $this->resource . '.' . $this->attribute . '.' . $this->rule
            . ' on ' . strtoupper($this->verb) . ' ' . $this->uriTemplate;
    }
}
