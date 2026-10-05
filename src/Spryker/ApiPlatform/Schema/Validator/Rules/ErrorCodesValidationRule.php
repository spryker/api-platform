<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

use Spryker\ApiPlatform\Generator\DeclaredErrorCodeResolver;

/**
 * Rejects an error-code declaration the generator could not emit: `codes` on a success status, an
 * entry without a string `code`, or a `commonErrorCodes` entry without an error status. Reported
 * before generation, all at once, rather than one at a time as the generator meets them.
 */
class ErrorCodesValidationRule implements ValidationRuleInterface
{
    public function __construct(protected DeclaredErrorCodeResolver $declaredErrorCodeResolver)
    {
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        return $this->declaredErrorCodeResolver->validate($schema);
    }
}
