<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection;

use Codeception\Test\Unit;
use ReflectionProperty;
use Spryker\ApiPlatform\DependencyInjection\Compiler\ApiClassAutoDiscoveryPass;
use Spryker\ApiPlatform\DependencyInjection\Compiler\RelationshipConfigurationPass;
use Spryker\ApiPlatform\DependencyInjection\Compiler\SchemaServiceRegistrationPass;
use Spryker\ApiPlatform\DependencyInjection\Compiler\SecurityServiceRegistrationPass;
use Spryker\ApiPlatform\SprykerApiPlatformBundle;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Guards the shared-instance wiring in the bundle: all schema-reading compiler passes must share one
 * SchemaFileDiscovery (and one ApiDirectoryLocator) so the filesystem is scanned only once per
 * container build. Every pass constructor has a `new ...()` default, so a regression to per-pass
 * instances would compile and pass the functional suite while silently re-scanning per pass.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group SprykerApiPlatformBundleTest
 * Add your own group annotations below this line
 */
class SprykerApiPlatformBundleTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenBundleBuildWhenPassesAreRegisteredThenTheyShareOneDiscoveryAndLocatorInstance(): void
    {
        // Arrange
        $containerBuilder = new ContainerBuilder();

        // Act
        (new SprykerApiPlatformBundle())->build($containerBuilder);

        // Assert
        $passesByClass = [];

        foreach ($containerBuilder->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            $passesByClass[get_class($pass)] = $pass;
        }

        $schemaFileDiscovery = $this->readProperty($passesByClass[SchemaServiceRegistrationPass::class], 'schemaFileDiscovery');

        $this->assertSame(
            $schemaFileDiscovery,
            $this->readProperty($passesByClass[RelationshipConfigurationPass::class], 'schemaFileDiscovery'),
        );
        $this->assertSame(
            $schemaFileDiscovery,
            $this->readProperty($passesByClass[SecurityServiceRegistrationPass::class], 'schemaFileDiscovery'),
        );
        $this->assertSame(
            $this->readProperty($schemaFileDiscovery, 'apiDirectoryLocator'),
            $this->readProperty($passesByClass[ApiClassAutoDiscoveryPass::class], 'apiDirectoryLocator'),
        );
    }

    protected function readProperty(object $object, string $propertyName): mixed
    {
        return (new ReflectionProperty($object, $propertyName))->getValue($object);
    }
}
