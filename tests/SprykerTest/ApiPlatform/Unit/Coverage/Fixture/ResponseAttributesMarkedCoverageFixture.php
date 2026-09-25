<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;
use Spryker\ApiPlatform\Contract\Attribute\CoversApiRequiredResponseAttributes;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\ContractCoverageRunnerTest}:
 * the same declaration as {@see ResponseAttributesDeclaringCoverageFixture} with the response
 * attribute marker on it, which closes every attribute of the operation it names at once.
 */
class ResponseAttributesMarkedCoverageFixture
{
    #[CoversApiOperation('GET', '/response-attributes-fixture')]
    #[CoversApiRequiredResponseAttributes]
    public function testGivenTheFixtureResourceWhenGetCollectionThenAllRequiredResponseAttributesAreReturned(): void
    {
    }
}
