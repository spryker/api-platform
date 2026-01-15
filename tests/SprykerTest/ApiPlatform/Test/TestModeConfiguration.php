<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

/**
 * Provides test configuration for API Platform tests.
 *
 * The mode is set by ApiPlatformHelper during suite initialization.
 * Configuration is read from the suite's modules section:
 *
 * ```yaml
 * modules:
 *   enabled:
 *     - \SprykerTest\ApiPlatform\Helper\ApiPlatformHelper:
 *         mode: 'project' # or 'core'
 * ```
 */
class TestModeConfiguration
{
    protected static ?TestMode $testMode = null;

    /**
     * Sets the test mode. Called by ApiPlatformHelper during initialization.
     */
    public static function setTestMode(TestMode $mode): void
    {
        static::$testMode = $mode;
    }

    /**
     * Returns the current test mode.
     *
     * Default mode is 'project' when not explicitly configured.
     */
    public static function getTestMode(): TestMode
    {
        return static::$testMode ?? TestMode::default();
    }

    /**
     * Checks if the current test mode is 'project'.
     */
    public static function isProjectMode(): bool
    {
        return static::getTestMode() === TestMode::PROJECT;
    }

    /**
     * Checks if the current test mode is 'core'.
     */
    public static function isCoreMode(): bool
    {
        return static::getTestMode() === TestMode::CORE;
    }

    /**
     * Resets the cached test mode. Useful for testing.
     */
    public static function reset(): void
    {
        static::$testMode = null;
    }
}
