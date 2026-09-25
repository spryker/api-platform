<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageRunner;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;

/**
 * The project's exclusion list reaches the gate along a chain - package configuration, container
 * parameter, the runner service's `$excludedResources` argument. The container link has its own
 * test; this pins the last one, because losing it silently moves a project decision back into the
 * core module. A missing default is what turns that loss into a container-compilation error, and
 * the same holds for `$apiType`: a default would let an application measure another one's
 * resources.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ContractCoverageScopeWiringTest
 * Add your own group annotations below this line
 */
class ContractCoverageScopeWiringTest extends Unit
{
    public function testGivenAnExcludedResourceListWhenConstructingTheRunnerThenItCarriesThatList(): void
    {
        // Arrange & Act
        $contractCoverageRunner = ContractCoverageFactory::createContractCoverageRunner('Storefront', ['wishlists']);

        // Assert
        $excludedResources = (new ReflectionProperty(ContractCoverageRunner::class, 'excludedResources'))
            ->getValue($contractCoverageRunner);
        $this->assertSame(['wishlists'], $excludedResources);
    }

    public function testGivenTheRunnerWhenReadingItsConstructorThenTheExcludedListCarriesNoDefault(): void
    {
        // Arrange & Act - the signature is the seam: the effect of a missing default is a
        // compilation failure of a container this lane never builds.
        $excludedResources = new ReflectionParameter([ContractCoverageRunner::class, '__construct'], 'excludedResources');

        // Assert
        $this->assertFalse($excludedResources->isDefaultValueAvailable());
    }

    public function testGivenTheRunnerWhenReadingItsConstructorThenTheApiTypeCarriesNoDefault(): void
    {
        // Arrange & Act
        $apiType = new ReflectionParameter([ContractCoverageRunner::class, '__construct'], 'apiType');

        // Assert
        $this->assertFalse($apiType->isDefaultValueAvailable());
    }

    public function testGivenTwoApiTypesWhenResolvingTheGeneratedResourcePathThenEachOneReadsItsOwnDirectory(): void
    {
        // Arrange
        $storefrontRunner = ContractCoverageFactory::createContractCoverageRunner('Storefront');
        $backendRunner = ContractCoverageFactory::createContractCoverageRunner('backend');

        // Act
        $storefrontPath = $this->invokeGeneratedResourcePath($storefrontRunner);
        $backendPath = $this->invokeGeneratedResourcePath($backendRunner);

        // Assert
        $this->assertSame('/project/src/Generated/Api/Storefront', $storefrontPath);
        $this->assertSame('/project/src/Generated/Api/Backend', $backendPath);
    }

    protected function invokeGeneratedResourcePath(ContractCoverageRunner $contractCoverageRunner): string
    {
        $generatedResourcePath = new ReflectionMethod($contractCoverageRunner, 'generatedResourcePath');

        return $generatedResourcePath->invoke($contractCoverageRunner, '/project');
    }
}
