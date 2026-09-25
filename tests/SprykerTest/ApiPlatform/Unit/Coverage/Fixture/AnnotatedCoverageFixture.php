<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequiredResponseAttributes;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiValidation;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\AnnotationCollectorTest}. Not
 * a runnable test (no `Test` suffix, so Codeception ignores it); the collector only reflects over
 * its `test*` methods to read the coverage attributes.
 */
class AnnotatedCoverageFixture
{
    #[CoversApiOperation('GET', '/wishlists')]
    public function testGivenSomethingWhenGetCollectionThenItIsReturned(): void
    {
    }

    #[CoversApiOperation('POST', '/wishlists')]
    #[CoversApiValidation('wishlists', 'name', 'NotBlank')]
    public function testGivenABlankNameWhenPostThenItFails(): void
    {
    }

    public function testGivenAnUnannotatedMethodWhenCollectingThenItContributesNothing(): void
    {
    }

    #[CoversApiOperation('GET', '/wishlists/{uuid}', status: 404)]
    public function testGivenAnUnknownUuidWhenGetThenItRespondsNotFound(): void
    {
    }

    #[CoversApiOperation('DELETE', '/wishlists/{uuid}')]
    protected function helperThatIsNotATestMethod(): void
    {
    }

    #[CoversApiOperation('GET', '/fixtures')]
    public function testGivenFixturesWhenGetCollectionThenNamesAreReturned(): void
    {
    }

    #[CoversApiOperation('POST', '/fixtures')]
    #[CoversApiRequiredResponseAttributes]
    public function testGivenAValidPayloadWhenTheEntityIsCreatedThenAllRequiredResponseAttributesAreReturned(): void
    {
    }
}
