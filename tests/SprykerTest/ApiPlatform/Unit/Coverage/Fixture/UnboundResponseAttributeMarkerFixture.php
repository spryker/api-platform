<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequiredResponseAttributes;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: a
 * response-attribute marker with no success operation on the method — the authoring error the
 * collector must reject, because response attribute coverage is per operation.
 */
class UnboundResponseAttributeMarkerFixture
{
    #[CoversApiRequiredResponseAttributes]
    public function testGivenAMarkerWithoutASuccessOperationWhenCollectingThenItIsRejected(): void
    {
    }
}
