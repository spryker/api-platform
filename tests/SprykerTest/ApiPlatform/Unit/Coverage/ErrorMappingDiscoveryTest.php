<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\ErrorMappingDiscovery;
use Spryker\ApiPlatform\Contract\Coverage\ErrorMappingResolver;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping\FixtureModuleGlueConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ErrorMappingDiscoveryTest
 * Add your own group annotations below this line
 */
class ErrorMappingDiscoveryTest extends Unit
{
    protected const string SCHEMA_FILE = 'src/ErrorMappingFixture/CartsFixture/resources/api/storefront/carts.resource.yml';

    protected const string PACKAGE_SCHEMA_FILE = '/data/shop/vendor/error-mapping-fixture/carts-fixture/resources/api/storefront/carts.resource.yml';

    protected const string MAPPING_SOURCE = 'ErrorMappingFixture\\Glue\\CartsFixture\\CartsFixtureConfig::getErrorIdentifierToRestErrorMapping';

    protected const string FIXTURE_CONFIG_CLASS = 'ErrorMappingFixture\\Glue\\CartsFixture\\CartsFixtureConfig';

    protected function _before(): void
    {
        if (!class_exists(static::FIXTURE_CONFIG_CLASS)) {
            class_alias(FixtureModuleGlueConfig::class, static::FIXTURE_CONFIG_CLASS);
        }
    }

    public function testGivenAModuleConfigWithAnErrorMappingMethodNobodyRegistersWhenDiscoveringThenItIsListed(): void
    {
        // Act
        $unregistered = (new ErrorMappingDiscovery(new ErrorMappingResolver([])))->unregisteredMappings([static::SCHEMA_FILE], []);

        // Assert
        $this->assertSame([static::MAPPING_SOURCE], $unregistered);
    }

    public function testGivenARegisteredMappingWhenDiscoveringThenItIsNotListed(): void
    {
        // Act
        $unregistered = (new ErrorMappingDiscovery(new ErrorMappingResolver([])))->unregisteredMappings([static::SCHEMA_FILE], [static::MAPPING_SOURCE]);

        // Assert
        $this->assertSame([], $unregistered);
    }

    public function testGivenASchemaFileOfAModuleInstalledUnderVendorWhenDiscoveringThenItsUnregisteredMappingIsListed(): void
    {
        // Act
        $unregistered = (new ErrorMappingDiscovery(new ErrorMappingResolver([])))->unregisteredMappings([static::PACKAGE_SCHEMA_FILE], []);

        // Assert
        $this->assertSame([static::MAPPING_SOURCE], $unregistered);
    }

    public function testGivenTheSameModuleUnderSrcAndVendorWhenDiscoveringThenItsMappingIsListedOnce(): void
    {
        // Act
        $unregistered = (new ErrorMappingDiscovery(new ErrorMappingResolver([])))->unregisteredMappings([static::SCHEMA_FILE, static::PACKAGE_SCHEMA_FILE], []);

        // Assert
        $this->assertSame([static::MAPPING_SOURCE], $unregistered);
    }
}
