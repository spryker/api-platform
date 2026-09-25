<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

/**
 * Seam fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\ContractCoverageRunnerTest}: the
 * same discovery as {@see UndeclaredResponsesContractCoverageRunner}, excluding both the resource
 * it does generate and one short name no resource carries.
 */
class UnknownExclusionContractCoverageRunner extends UndeclaredResponsesContractCoverageRunner
{
    public function __construct()
    {
        parent::__construct(['undeclared', 'renamed-away']);
    }
}
