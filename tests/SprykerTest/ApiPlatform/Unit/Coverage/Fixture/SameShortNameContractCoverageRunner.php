<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

/**
 * Seam fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\ContractCoverageRunnerTest}: pins
 * the discovery to two fixture classes that share one short name, so the truth-merging behaviour is
 * testable without the filesystem scan.
 */
class SameShortNameContractCoverageRunner extends AbstractFixtureContractCoverageRunner
{
    protected function discoverResourceClasses(string $directory): array
    {
        return [
            SameShortNameFixtureResource::class,
            SameShortNameNestedRouteFixtureResource::class,
        ];
    }

    protected function discoverTestClasses(string $testsRoot): array
    {
        return [];
    }
}
