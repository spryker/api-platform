<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping;

/**
 * A module Glue config at the place discovery looks for one - `<Organization>\Glue\<Module>\<Module>Config` -
 * for the schema file `src/ErrorMappingFixture/CartsFixture/resources/api/storefront/carts.resource.yml`.
 * The test aliases it to that name.
 */
class FixtureModuleGlueConfig
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getErrorIdentifierToRestErrorMapping(): array
    {
        return ['cart.not-found' => ['code' => '101', 'status' => 404]];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getCartSettings(): array
    {
        return [];
    }
}
