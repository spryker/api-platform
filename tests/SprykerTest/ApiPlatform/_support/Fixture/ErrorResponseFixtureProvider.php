<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * Declares the `*NOT_FOUND*MESSAGE` / `*NOT_FOUND*CODE` constant pair that the runtime reflects on
 * when it renders a resource-specific 404 error.
 *
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
class ErrorResponseFixtureProvider implements ProviderInterface
{
    public const string ERROR_CODE_CATEGORY_NOT_FOUND = '1301';

    public const string ERROR_MESSAGE_CATEGORY_NOT_FOUND = 'Category not found.';

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return null;
    }
}
