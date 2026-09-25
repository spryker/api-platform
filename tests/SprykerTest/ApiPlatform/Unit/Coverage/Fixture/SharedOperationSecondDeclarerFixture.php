<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: the
 * second class declaring the same success operation as
 * {@see \SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SharedOperationFirstDeclarerFixture}. Not a
 * runnable test (no `Test` suffix, so Codeception ignores it).
 */
class SharedOperationSecondDeclarerFixture
{
    #[CoversApiOperation('GET', '/shared-declarers')]
    public function testGivenSharedDeclarersWhenGetCollectionThenTheEnvelopeIsCorrect(): void
    {
    }
}
