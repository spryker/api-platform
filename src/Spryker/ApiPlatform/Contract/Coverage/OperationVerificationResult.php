<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * What one test method's runtime recording proved, and what it contradicted: declarations no
 * observed response satisfied, and responses the resource schema does not declare.
 */
readonly class OperationVerificationResult
{
    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $unverified
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $undeclaredObservations
     */
    public function __construct(
        public array $unverified,
        public array $undeclaredObservations,
    ) {
    }
}
