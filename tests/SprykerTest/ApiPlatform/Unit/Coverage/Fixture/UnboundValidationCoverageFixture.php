<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}: a
 * validation declaration with no operation on the method — the authoring error the collector must
 * reject, because a validation rule binds to the operation it is exercised against.
 */
class UnboundValidationCoverageFixture
{
    #[CoversApiValidation('wishlists', 'name', 'NotBlank')]
    public function testGivenAValidationWithoutAnOperationWhenCollectingThenItIsRejected(): void
    {
    }
}
