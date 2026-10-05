<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping;

/**
 * The project override of {@see FixtureOverriddenErrorMappingConfig}, adding one mapped error. The test
 * aliases it to the core class name under a fake project namespace, which is where the resolver
 * looks for an override.
 */
class FixtureProjectErrorMappingOverride extends FixtureOverriddenErrorMappingConfig
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getErrorIdentifierToRestErrorMapping(): array
    {
        return parent::getErrorIdentifierToRestErrorMapping() + [
            'checkout.cart-empty' => ['code' => '1105', 'status' => 422, 'detail' => 'Cart is empty.'],
        ];
    }
}
