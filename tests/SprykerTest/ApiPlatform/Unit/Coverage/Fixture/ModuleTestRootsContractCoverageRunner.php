<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageRunner;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;

class ModuleTestRootsContractCoverageRunner extends ContractCoverageRunner
{
    /**
     * @param array<string> $moduleSourceDirectories
     */
    public function __construct(array $moduleSourceDirectories)
    {
        parent::__construct(
            'Storefront',
            [],
            ...[...ContractCoverageFactory::createContractCoverageRunnerCollaborators(), $moduleSourceDirectories],
        );
    }

    /**
     * @return array<string>
     */
    public function findTestRoots(string $applicationRoot): array
    {
        return $this->getTestRoots($applicationRoot);
    }
}
