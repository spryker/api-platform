<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMapping;

/**
 * An instance error mapping in the shape the Glue module configs use, next to the shapes the
 * resolver rejects.
 */
class FixtureErrorMappingConfig
{
    /**
     * @return array<string, mixed>
     */
    public function getErrorIdentifierToRestErrorMapping(): array
    {
        return [
            'cart.not-found' => ['code' => '101', 'status' => 404, 'detail' => 'Cart not found.'],
            'cart.locked' => ['code' => '118', 'status' => 422, 'detail' => 'Cart is locked.'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getErrorMessageToErrorIdentifierMapping(): array
    {
        return ['Cart is gone.' => 'cart.not-found'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getErrorMappingWithAnEntryWithoutCode(): array
    {
        return [
            'cart.not-found' => ['code' => '101', 'status' => 404],
            'cart.locked' => ['status' => 422, 'detail' => 'Cart is locked.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEmptyErrorMapping(): array
    {
        return [];
    }

    public function getNonArrayErrorMapping(): string
    {
        return 'cart.not-found';
    }
}
