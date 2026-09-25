<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

/**
 * Seam fixture for {@see \SprykerTest\ApiPlatform\Unit\Coverage\ContractCoverageRunnerTest}: pins
 * discovery to the three shapes the envelope's identifier exemption has to tell apart — a plain
 * declared identifier, no identifier at all, and an identifier the schema also marks
 * `readable: false` — so all three are provable without generating a resource.
 */
class IdentifierDeclarationContractCoverageRunner extends AbstractFixtureContractCoverageRunner
{
    protected function discoverResourceClasses(string $directory): array
    {
        return [
            UndeclaredResponsesFixtureResource::class,
            IdentifierlessFixtureResource::class,
            UnreadableIdentifierFixtureResource::class,
        ];
    }

    protected function discoverTestClasses(string $testsRoot): array
    {
        return [];
    }
}
