<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: an
 * error code declared without the error status it narrows - the authoring error the collector
 * must reject.
 */
class CodeWithoutStatusCoverageFixture
{
    #[CoversApiOperation('DELETE', '/carts/{cartUuid}/cart-codes/{code}', code: '3301')]
    public function testGivenACodeWithoutAStatusWhenCollectingThenItIsRejected(): void
    {
    }
}
