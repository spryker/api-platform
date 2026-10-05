<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiIncludes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: an
 * include claim without a success operation, and one without a relationship name.
 */
class IncludesMarkedCoverageFixture
{
    #[CoversApiOperation('GET', '/carts/{cartUuid}', status: 404)]
    #[CoversApiIncludes('vouchers')]
    public function testGivenAnErrorOperationWhenIncludingThenTheClaimIsRejected(): void
    {
    }
}
