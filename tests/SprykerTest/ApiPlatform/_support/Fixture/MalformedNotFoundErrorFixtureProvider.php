<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

class MalformedNotFoundErrorFixtureProvider implements ProviderInterface
{
    public const MalformedNotFoundErrorFixtureValue ERROR_MESSAGE_CATEGORY_NOT_FOUND = MalformedNotFoundErrorFixtureValue::NotAString;

    public const MalformedNotFoundErrorFixtureValue ERROR_CODE_CATEGORY_NOT_FOUND = MalformedNotFoundErrorFixtureValue::NotAString;

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return null;
    }
}
