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
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: a
 * request attribute marker on a read - the authoring error the collector must reject.
 */
class UnboundRequestAttributeMarkerFixture
{
    #[CoversApiOperation('GET', '/carts')]
    #[CoversApiRequestAttributes]
    public function testGivenAReadWhenCollectingThenTheMarkerIsRejected(): void
    {
    }
}
