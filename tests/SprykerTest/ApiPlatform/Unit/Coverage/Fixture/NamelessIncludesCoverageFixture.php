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
 * include claim that names no relationship.
 */
class NamelessIncludesCoverageFixture
{
    #[CoversApiOperation('GET', '/carts/{cartUuid}')]
    #[CoversApiIncludes]
    public function testGivenNoRelationshipNameWhenIncludingThenTheClaimIsRejected(): void
    {
    }
}
