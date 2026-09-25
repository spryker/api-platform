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
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;

/**
 * A resource addressed only by id: the bare collection URL exists so an old client gets the
 * documented error rather than a route-not-found, and answers no resource body at all.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/error-only/{uuid}',
            openapi: new Operation(responses: [200 => new OpenApiResponse(description: 'Returned.')]),
        ),
        new GetCollection(
            uriTemplate: '/error-only',
            openapi: new Operation(responses: [400 => new OpenApiResponse(description: 'Id has not been specified.')]),
        ),
    ],
    shortName: 'error-only',
    provider: 'SomeProvider',
)]
class ErrorOnlyCollectionFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;

    #[ApiProperty]
    public ?string $name = null;
}
