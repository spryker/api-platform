<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\ApiProperty;

/**
 * The nested object one level below {@see ResponseAttributesFixtureResource}: a required field the
 * derivation must descend into, an opted-out one proving the skip rules apply recursively, and an
 * object of its own, which the depth-1 stop must reduce to a presence path.
 */
class ResponseAttributesFixturePaginationObject
{
    public ?int $numFound = null;

    #[ApiProperty(extraProperties: ['responseOptional' => true])]
    public ?int $maxPage = null;

    public ?ResponseAttributesFixtureCursorObject $cursor = null;
}
