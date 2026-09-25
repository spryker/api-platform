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
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;

/**
 * The top-level schema file of a resource that two files back. Shares its short name with
 * {@see \SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SameShortNameNestedRouteFixtureResource}.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/same-short-name-fixture',
            openapi: new Operation(
                tags: ['same-short-name-fixture'],
                responses: [
                    '200' => new OpenApiResponse(description: 'A collection of fixtures.'),
                ],
            ),
        ),
    ],
    shortName: 'same-short-name-fixture',
)]
class SameShortNameFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;
}
