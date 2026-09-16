<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

/**
 * Defines the test execution mode for API Platform tests.
 *
 * - CORE: For module-level testing. Resources are generated and cleaned up automatically.
 *         Uses tests/_data/Api/{ApiType} paths. Container is compiled fresh each time.
 *
 * - PROJECT: For project-level testing. Resources must be pre-generated manually via
 *            `vendor/bin/console api:generate backend` or `api:generate storefront`.
 *            Uses src/Generated/Api/{ApiType} paths. Container is reused if available.
 */
enum TestMode: string
{
    case CORE = 'core';
    case PROJECT = 'project';

    /**
     * Returns the default test mode when not explicitly configured.
     */
    public static function default(): self
    {
        return static::PROJECT;
    }

    /**
     * Creates a TestMode from a string value, with fallback to default.
     *
     * @param string|null $value The mode string value
     *
     * @return self The resolved TestMode
     */
    public static function fromString(?string $value): self
    {
        if ($value === null) {
            return static::default();
        }

        return static::tryFrom(strtolower($value)) ?? static::default();
    }
}
