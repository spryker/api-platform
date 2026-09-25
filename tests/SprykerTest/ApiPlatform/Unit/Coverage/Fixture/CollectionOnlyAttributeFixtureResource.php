<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;

/**
 * A resource whose pagination metadata belongs to the collection response alone and whose detail
 * belongs to the item response alone, alongside an attribute every operation answers.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/paginated',
            openapi: new Operation(responses: [200 => new OpenApiResponse(description: 'Listed.')]),
        ),
        new Post(
            uriTemplate: '/paginated',
            openapi: new Operation(responses: [201 => new OpenApiResponse(description: 'Created.')]),
        ),
    ],
    shortName: 'paginated',
    provider: 'SomeProvider',
)]
class CollectionOnlyAttributeFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;

    #[ApiProperty]
    public ?string $name = null;

    #[ApiProperty(extraProperties: ['collectionOnly' => true])]
    public ?int $numFound = null;

    #[ApiProperty(extraProperties: ['itemOnly' => true])]
    public ?string $detail = null;
}
