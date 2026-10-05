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
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A resource addressed only by id: the bare collection URL exists so an old client gets the
 * documented error rather than a route-not-found, and answers no resource body at all. Its create
 * is kept only to answer the legacy error, and declares no success either.
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
        new Post(
            uriTemplate: '/error-only',
            openapi: new Operation(responses: [
                400 => new OpenApiResponse(description: 'Creating is not supported.'),
                422 => new OpenApiResponse(description: 'Request validation failed.'),
            ]),
        ),
    ],
    shortName: 'error-only',
    provider: 'SomeProvider',
    extraProperties: ['declaredIncludes' => ['owners' => ['Get', 'GetCollection']]],
)]
class ErrorOnlyCollectionFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;

    #[ApiProperty]
    #[NotBlank]
    public ?string $name = null;

    /**
     * @var array<string>
     */
    #[ApiProperty]
    public array $tags = [];
}
