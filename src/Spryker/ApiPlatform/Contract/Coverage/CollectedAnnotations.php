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
     * @param array<string, array<string>> $errorResponseDeclarers `VERB uri status` => the tests declaring that error response without a code
     * @param array<string, array<string>|true> $requestAttributeClaims dispatch key => the request attribute paths claimed for it, `true` for all
     * @param array<string, array<string>> $includeClaims dispatch key => the relationship names claimed for it
     * @param array<string> $replayedResources The resource short names example-replay test classes name.
     */
    public function __construct(
        public array $declaredOperations,
        public array $declaredValidations,
        public array $responseAttributeCoveredOperations = [],
        public array $operationDeclarers = [],
        public array $errorResponseDeclarers = [],
        public array $requestAttributeClaims = [],
        public array $includeClaims = [],
        public array $replayedResources = [],
    ) {
    }
}
