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
 * Mirrors the generated shape of a Storefront operation whose 403 declares a code of its own and is
 * joined by a shared code of the API.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/shared-error-codes',
            openapi: new Operation(responses: [
                '200' => new OpenApiResponse(description: 'Returned.'),
                '403' => new OpenApiResponse(description: 'Forbidden.'),
            ]),
            extraProperties: ['declaredErrorCodes' => [403 => ['802']], 'sharedErrorCodes' => [403 => ['803']]],
        ),
    ],
    shortName: 'shared-error-codes',
)]
class SharedErrorCodesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;
}
