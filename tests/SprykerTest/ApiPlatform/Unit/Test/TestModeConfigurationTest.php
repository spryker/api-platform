<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Test;

use Codeception\Test\Unit;
use SprykerTest\ApiPlatform\Test\TestMode;
use SprykerTest\ApiPlatform\Test\TestModeConfiguration;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Test
 * @group TestModeConfigurationTest
 * Add your own group annotations below this line
 */
class TestModeConfigurationTest extends Unit
{
    protected TestMode $suiteMode;

    protected function setUp(): void
    {
        parent::setUp();

        // Capture the suite-level mode so tearDown can restore it. The shared
        // Testify kernel keys its container cache on this mode; leaving it
        // flipped would force a host-lane recompile that fails on config/Glue.
        $this->suiteMode = TestModeConfiguration::getTestMode();
    }

    protected function tearDown(): void
    {
        TestModeConfiguration::reset();
        TestModeConfiguration::setTestMode($this->suiteMode);
        parent::tearDown();
    }

    public function testGivenNoConfigWhenReadingFlagsThenDefaultsPreserveCurrentBehaviour(): void
    {
        // Arrange — clear fast-path flags without disturbing the suite mode
        TestModeConfiguration::reset();
        TestModeConfiguration::setTestMode($this->suiteMode);

        // Act & Assert — defaults must equal today's hardcoded behaviour
        $this->assertTrue(TestModeConfiguration::isDebug());
        $this->assertFalse(TestModeConfiguration::isBootOnce());
        $this->assertFalse(TestModeConfiguration::isReuseApplicationContainer());
    }

    public function testGivenFastPathSetWhenReadingFlagsThenOptInValuesReturned(): void
    {
        // Arrange
        TestModeConfiguration::setDebug(false);
        TestModeConfiguration::setBootOnce(true);
        TestModeConfiguration::setReuseApplicationContainer(true);

        // Act & Assert
        $this->assertFalse(TestModeConfiguration::isDebug());
        $this->assertTrue(TestModeConfiguration::isBootOnce());
        $this->assertTrue(TestModeConfiguration::isReuseApplicationContainer());
    }
}
