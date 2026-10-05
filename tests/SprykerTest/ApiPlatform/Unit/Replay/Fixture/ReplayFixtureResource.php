<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Replay\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;

/**
 * A resource whose properties carry examples the replay builds from: a writable one, a read-only
 * one, one writable only on create, and a nested value object. Its item read has no provider, so it
 * is not servable and not replayed.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/customers/{customerReference}/replay-items',
            openapi: new Operation(parameters: [
                new Parameter(name: 'page[limit]', in: 'query', example: 5),
                new Parameter(name: 'filter[store]', in: 'query', required: true),
                new Parameter(name: 'sort', in: 'query'),
            ]),
        ),
        new Get(uriTemplate: '/replay-items/{uuid}'),
        new Post(uriTemplate: '/replay-items'),
        new Patch(uriTemplate: '/replay-items/{uuid}', provider: 'replay-items-provider'),
    ],
    shortName: 'replay-items',
)]
class ReplayFixtureResource
{
    #[ApiProperty(identifier: true, openapiContext: ['example' => 'uuid-example'])]
    public ?string $uuid = null;

    #[ApiProperty(openapiContext: ['example' => 'Wishlist'])]
    public ?string $name = null;

    #[ApiProperty(writable: false, openapiContext: ['example' => '2026-01-01'])]
    public ?string $createdAt = null;

    #[ApiProperty(extraProperties: ['writableOn' => ['Post']], openapiContext: ['example' => 'DE'])]
    public ?string $store = null;

    public ?ReplayFixtureSalesUnitObject $salesUnit = null;
}
