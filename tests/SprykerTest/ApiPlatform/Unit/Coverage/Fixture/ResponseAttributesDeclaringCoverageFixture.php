<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Contract\Attribute\CoversApiOperation;

/**
 * Reflection fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\ContractCoverageRunnerTest}:
 * declares the fixture resource's collection GET without claiming its response attributes — the
 * state every adopted operation starts in, and the one the gate has to report as a gap.
 */
class ResponseAttributesDeclaringCoverageFixture
{
    #[CoversApiOperation('GET', '/response-attributes-fixture')]
    public function testGivenTheFixtureResourceWhenGetCollectionThenItIsReturned(): void
    {
    }
}
