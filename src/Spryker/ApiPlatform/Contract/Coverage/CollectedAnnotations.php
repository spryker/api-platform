<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The coverage claims collected from the `#[CoversApiOperation]` / `#[CoversApiValidation]` /
 * `#[CoversApiRequiredResponseAttributes]` annotations across a set of test classes.
 */
readonly class CollectedAnnotations
{
    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ApiOperation> $declaredOperations
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint> $declaredValidations
     * @param array<string> $responseAttributeCoveredOperations dispatch keys whose test carries `#[CoversApiRequiredResponseAttributes]`
     * @param array<string, array<string>> $operationDeclarers dispatch key => `Fully\Qualified\Test::testMethod` for its success declarations
     */
    public function __construct(
        public array $declaredOperations,
        public array $declaredValidations,
        public array $responseAttributeCoveredOperations = [],
        public array $operationDeclarers = [],
    ) {
    }
}
