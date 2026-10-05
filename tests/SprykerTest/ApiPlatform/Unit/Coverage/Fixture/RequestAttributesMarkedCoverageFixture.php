<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequestAttributes;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: one
 * test claims every request attribute of its operation, two others split one operation's set.
 */
class RequestAttributesMarkedCoverageFixture
{
    #[CoversApiOperation('POST', '/carts')]
    #[CoversApiRequestAttributes]
    public function testGivenEveryAttributeWhenCreatingThenTheCartIsCreated(): void
    {
    }

    #[CoversApiOperation('PATCH', '/carts/{cartUuid}')]
    #[CoversApiRequestAttributes('currency')]
    public function testGivenACurrencyWhenPatchingThenItIsChanged(): void
    {
    }

    #[CoversApiOperation('PATCH', '/carts/{cartUuid}')]
    #[CoversApiRequestAttributes('priceMode', 'name')]
    public function testGivenAPriceModeWhenPatchingThenItIsChanged(): void
    {
    }
}
