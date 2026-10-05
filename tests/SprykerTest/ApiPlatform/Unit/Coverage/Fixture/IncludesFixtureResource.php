<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;

/**
 * Mirrors the generated shape of a resource with two includes: one owed by every read, one
 * narrowed to the item read with `includedOn`. The resource has no provider, so its item read is
 * non-servable and owes nothing.
 */
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/carts'),
        new Get(uriTemplate: '/carts/{uuid}'),
        new Post(uriTemplate: '/carts'),
    ],
    shortName: 'carts',
    extraProperties: ['declaredIncludes' => ['items' => ['Get', 'GetCollection'], 'vouchers' => ['Get']]],
)]
class IncludesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;
}
