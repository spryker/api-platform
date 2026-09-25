<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: one
 * of two classes declaring the same success operation, so the collector's per-key merge across
 * classes is exercised. Not a runnable test (no `Test` suffix, so Codeception ignores it).
 */
class SharedOperationFirstDeclarerFixture
{
    #[CoversApiOperation('GET', '/shared-declarers')]
    public function testGivenSharedDeclarersWhenGetCollectionThenTheyAreReturned(): void
    {
    }
}
