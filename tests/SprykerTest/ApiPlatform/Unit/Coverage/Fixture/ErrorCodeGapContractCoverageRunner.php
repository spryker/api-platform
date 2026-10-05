<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageBaseline;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageRunner;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;

/**
 * Seam fixture for {@see \SprykerTest\ApiPlatform\Unit\Command\ApiContractCoverageCommandTest}: its
 * one resource is excluded, so the always-enforced dimensions pass, and its calculator reports one
 * error-code gap whose verdict only the enforcement decides.
 */
class ErrorCodeGapContractCoverageRunner extends ContractCoverageRunner
{
    public function __construct(
        ContractCoverageEnforcement $enforcement = new ContractCoverageEnforcement(),
        ContractCoverageBaseline $baseline = new ContractCoverageBaseline(),
    ) {
        [$truthLoader, $annotationCollector, $scopeResolver, , $resourceNameMatcher, $schemaSourceResolver, $declaredClassNameResolver, , $errorMappingResolver, $errorMappingDiscovery]
            = ContractCoverageFactory::createContractCoverageRunnerCollaborators();

        parent::__construct(
            'Storefront',
            ['customer-password'],
            $truthLoader,
            $annotationCollector,
            $scopeResolver,
            new ErrorCodeGapCoverageCalculator(),
            $resourceNameMatcher,
            $schemaSourceResolver,
            $declaredClassNameResolver,
            $enforcement,
            $errorMappingResolver,
            $errorMappingDiscovery,
            $baseline,
        );
    }

    protected function discoverResourceClasses(string $directory): array
    {
        return [DeclaredResponsesFixtureResource::class];
    }

    protected function discoverTestClasses(string $testsRoot): array
    {
        return [];
    }
}
