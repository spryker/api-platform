<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

/**
 * The object two levels below {@see ResponseAttributesFixtureResource}. Its field must never reach
 * the derived path list: the derivation stops after one level, so the object above it is demanded
 * by presence alone.
 */
class ResponseAttributesFixtureCursorObject
{
    public ?string $after = null;
}
