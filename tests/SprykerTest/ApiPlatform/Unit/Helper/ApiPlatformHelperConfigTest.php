<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Helper;

use Codeception\Lib\ModuleContainer;
use Codeception\Test\Unit;
use SprykerTest\ApiPlatform\Helper\ApiPlatformHelper;
use SprykerTest\ApiPlatform\Test\TestMode;
use SprykerTest\ApiPlatform\Test\TestModeConfiguration;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Helper
 * @group ApiPlatformHelperConfigTest
 * Add your own group annotations below this line
 */
class ApiPlatformHelperConfigTest extends Unit
{
    protected TestMode $suiteMode;

    protected function setUp(): void
    {
        parent::setUp();

        // Capture the suite mode; tearDown restores it so the shared Testify
        // kernel cache stays keyed on the same mode between test methods.
        $this->suiteMode = TestModeConfiguration::getTestMode();
    }

    protected function tearDown(): void
    {
        TestModeConfiguration::reset();
        TestModeConfiguration::setTestMode($this->suiteMode);
        parent::tearDown();
    }

    public function testGivenFastPathConfigWhenInitialisedThenFlagsPushedToConfiguration(): void
    {
        // Arrange
        $moduleContainer = $this->createMock(ModuleContainer::class);
        $helper = new ApiPlatformHelper($moduleContainer, [
            'mode' => 'project',
            'debug' => false,
            'bootOnce' => true,
            'reuseApplicationContainer' => true,
        ]);

        // Act
        $helper->_initialize();

        // Assert
        $this->assertFalse(TestModeConfiguration::isDebug());
        $this->assertTrue(TestModeConfiguration::isBootOnce());
        $this->assertTrue(TestModeConfiguration::isReuseApplicationContainer());
    }

    public function testGivenNoFastPathConfigWhenInitialisedThenDefaultsUnchanged(): void
    {
        // Arrange
        $moduleContainer = $this->createMock(ModuleContainer::class);
        $helper = new ApiPlatformHelper($moduleContainer, ['mode' => 'project']);

        // Act
        $helper->_initialize();

        // Assert — current behaviour preserved
        $this->assertTrue(TestModeConfiguration::isDebug());
        $this->assertFalse(TestModeConfiguration::isBootOnce());
        $this->assertFalse(TestModeConfiguration::isReuseApplicationContainer());
    }
}
