<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageRunner;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;

/**
 * Base for the seam fixtures that replace the runner's discovery with a fixed class list. It hands
 * on the real collaborator graph, so a fixture states only the seam it overrides.
 */
abstract class AbstractFixtureContractCoverageRunner extends ContractCoverageRunner
{
    /**
     * The fixtures pin discovery to a fixed class list, so the API type only has to be a valid one.
     */
    protected const string API_TYPE = 'Storefront';

    /**
     * @param array<string> $excludedResources
     */
    public function __construct(array $excludedResources = [])
    {
        parent::__construct(
            static::API_TYPE,
            $excludedResources,
            ...ContractCoverageFactory::createContractCoverageRunnerCollaborators(),
        );
    }
}
