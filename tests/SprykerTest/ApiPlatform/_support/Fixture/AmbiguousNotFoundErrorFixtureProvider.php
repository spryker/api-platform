<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

class AmbiguousNotFoundErrorFixtureProvider implements ProviderInterface
{
    public const string ERROR_CODE_CUSTOMER_NOT_FOUND = '1201';

    public const string ERROR_MESSAGE_CUSTOMER_NOT_FOUND = 'Customer not found.';

    public const string ERROR_CODE_ADDRESS_NOT_FOUND = '1205';

    public const string ERROR_MESSAGE_ADDRESS_NOT_FOUND = 'Address not found.';

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return null;
    }
}
