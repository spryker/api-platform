<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;

/**
 * Mirrors the generated shape of a resource whose error statuses declare codes: a visible operation
 * with two codes on its 422, and a hidden one whose codes ride next to its declared responses.
 */
#[ApiResource(
    operations: [
        new Delete(
            uriTemplate: '/carts/{cartUuid}/cart-codes/{code}',
            openapi: new Operation(responses: [
                '204' => new OpenApiResponse(description: 'Removed.'),
                '422' => new OpenApiResponse(description: 'Cart code could not be removed.'),
            ]),
            extraProperties: ['declaredErrorCodes' => [422 => ['3301', '3303']]],
        ),
        new GetCollection(
            uriTemplate: '/cart-codes',
            openapi: false,
            extraProperties: ['declaredErrorCodes' => [400 => ['104']], 'declaredResponses' => [400]],
        ),
    ],
    shortName: 'cart-codes',
)]
class DeclaredErrorCodesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $code = null;
}
