<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

/**
 * Seam fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\ContractCoverageRunnerTest}: pins
 * discovery to one enforced resource whose operation declares no responses, so the schema-defect
 * path is testable without generating a broken resource.
 */
class UndeclaredResponsesContractCoverageRunner extends AbstractFixtureContractCoverageRunner
{
    protected function discoverResourceClasses(string $directory): array
    {
        return [UndeclaredResponsesFixtureResource::class];
    }

    protected function discoverTestClasses(string $testsRoot): array
    {
        return [];
    }
}
