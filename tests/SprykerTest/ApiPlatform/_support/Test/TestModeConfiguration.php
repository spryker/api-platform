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

    protected static ?bool $debug = null;

    protected static ?bool $bootOnce = null;

    protected static ?bool $reuseApplicationContainer = null;

    public static function setTestMode(TestMode $mode): void
    {
        static::$testMode = $mode;
    }

    /**
     * Default (null → true) preserves the historical hardcoded behaviour.
     */
    public static function setDebug(bool $debug): void
    {
        static::$debug = $debug;
    }

    public static function isDebug(): bool
    {
        return static::$debug ?? true;
    }

    /**
     * Default (null → false) preserves the historical per-method boot behaviour.
     */
    public static function setBootOnce(bool $bootOnce): void
    {
        static::$bootOnce = $bootOnce;
    }

    public static function isBootOnce(): bool
    {
        return static::$bootOnce ?? false;
    }

    /**
     * Default (null → false) preserves the historical per-method reset behaviour.
     */
    public static function setReuseApplicationContainer(bool $reuse): void
    {
        static::$reuseApplicationContainer = $reuse;
    }

    public static function isReuseApplicationContainer(): bool
    {
        return static::$reuseApplicationContainer ?? false;
    }

    /**
     * Default mode is 'project' when not explicitly configured.
     */
    public static function getTestMode(): TestMode
    {
        return static::$testMode ?? TestMode::default();
    }

    public static function isProjectMode(): bool
    {
        return static::getTestMode() === TestMode::PROJECT;
    }

    public static function isCoreMode(): bool
    {
        return static::getTestMode() === TestMode::CORE;
    }

    public static function reset(): void
    {
        static::$testMode = null;
        static::$debug = null;
        static::$bootOnce = null;
        static::$reuseApplicationContainer = null;
    }
}
