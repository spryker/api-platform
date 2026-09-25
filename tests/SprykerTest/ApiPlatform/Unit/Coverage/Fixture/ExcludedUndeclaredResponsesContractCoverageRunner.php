<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

/**
 * The excluded counterpart of {@see UndeclaredResponsesContractCoverageRunner}: the same
 * response-less resource, taken out of the gate by configuration, so the gate must stay quiet
 * about it.
 */
class ExcludedUndeclaredResponsesContractCoverageRunner extends UndeclaredResponsesContractCoverageRunner
{
    public function __construct()
    {
        parent::__construct(['undeclared']);
    }
}
