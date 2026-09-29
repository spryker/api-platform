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
 * A write accepted without a body: the create answers 202 and nothing else, while the collection
 * still returns the resource.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/bodyless-writes',
            output: false,
            status: 202,
            openapi: new Operation(responses: [202 => new OpenApiResponse(description: 'Accepted.')]),
        ),
        new GetCollection(
            uriTemplate: '/bodyless-writes',
            openapi: new Operation(responses: [200 => new OpenApiResponse(description: 'Returned.')]),
        ),
    ],
    shortName: 'bodyless-writes',
    provider: 'SomeProvider',
)]
class BodylessWriteFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;

    #[ApiProperty]
    public ?string $name = null;
}
