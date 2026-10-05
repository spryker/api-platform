<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping;

/**
 * The core side of a mapping a project overrides in {@see FixtureProjectErrorMappingOverride}.
 */
class FixtureOverriddenErrorMappingConfig
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getErrorIdentifierToRestErrorMapping(): array
    {
        return ['checkout.failed' => ['code' => '1104', 'status' => 422, 'detail' => 'Checkout failed.']];
    }
}
