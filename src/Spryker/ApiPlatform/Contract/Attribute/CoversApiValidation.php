<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Attribute;

use Attribute;

/**
 * Declares an input-validation constraint a test asserts on: the resource short name, the guarded
 * attribute and the rule identifier. Add only on tests that assert a 4xx validation outcome;
 * happy-path tests carry only {@see CoversApiOperation}.
 *
 * The rule is the Symfony constraint's own short name — `'NotBlank'`, `'Email'`, `'Type'` — except
 * for the {@see Rule} cases. It binds to the {@see CoversApiOperation} on the same method, without
 * which the coverage collection fails.
 */
#[\Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class CoversApiValidation
{
    public function __construct(
        public string $resource,
        public string $attribute,
        public Rule|string $rule,
    ) {
    }

    public function ruleIdentifier(): string
    {
        return $this->rule instanceof Rule ? $this->rule->value : $this->rule;
    }
}
