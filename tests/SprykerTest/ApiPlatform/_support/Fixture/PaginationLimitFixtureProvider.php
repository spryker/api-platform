<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

use ApiPlatform\Metadata\Operation;
use Spryker\ApiPlatform\State\Provider\AbstractProvider;

/**
 * Exposes the protected pagination helpers of {@see AbstractProvider} for direct unit testing.
 */
class PaginationLimitFixtureProvider extends AbstractProvider
{
    /**
     * @param array<string, mixed> $context
     */
    public function callGetPaginationLimit(Operation $operation, array $context, int $limit = self::DEFAULT_PER_PAGE): int
    {
        $this->operation = $operation;
        $this->context = $context;

        return $this->getPaginationLimit($limit);
    }
}
