<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\ReplaysOpenApiExamples;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: an
 * example-replay class naming two resources.
 */
#[ReplaysOpenApiExamples('carts', 'guest-carts')]
class ReplayingCoverageFixture
{
    public function testGivenTheGeneratedExampleWhenReplayedThenItAnswers(): void
    {
    }
}
