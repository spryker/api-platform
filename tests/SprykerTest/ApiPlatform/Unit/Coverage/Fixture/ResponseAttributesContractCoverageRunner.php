<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

/**
 * Seam fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\ContractCoverageRunnerTest}: pins
 * discovery to the response-attribute fixture resource and to the annotated fixture classes a case
 * hands it, so the response-attribute dimension is provable end to end without generating a
 * resource or reaching for the repository's real tests.
 */
class ResponseAttributesContractCoverageRunner extends AbstractFixtureContractCoverageRunner
{
    /**
     * @param array<string> $excludedResources
     * @param array<class-string> $annotatedTestClasses
     */
    public function __construct(array $excludedResources = [], protected array $annotatedTestClasses = [])
    {
        parent::__construct($excludedResources);
    }

    protected function discoverResourceClasses(string $directory): array
    {
        return [ResponseAttributesFixtureResource::class];
    }

    protected function discoverTestClasses(string $testsRoot): array
    {
        return $this->annotatedTestClasses;
    }
}
