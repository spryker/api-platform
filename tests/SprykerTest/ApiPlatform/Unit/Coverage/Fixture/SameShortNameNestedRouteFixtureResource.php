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
 * The nested-route schema file of the same resource as
 * {@see \SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SameShortNameFixtureResource}: one short
 * name, a second uriTemplate.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/parents/{parentReference}/same-short-name-fixture',
            openapi: new Operation(
                tags: ['same-short-name-fixture'],
                responses: [
                    '200' => new OpenApiResponse(description: 'A collection of fixtures of one parent.'),
                ],
            ),
        ),
    ],
    shortName: 'same-short-name-fixture',
)]
class SameShortNameNestedRouteFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;
}
