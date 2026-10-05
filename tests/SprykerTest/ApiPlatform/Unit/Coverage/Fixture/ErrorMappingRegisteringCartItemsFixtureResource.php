<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;

/**
 * A second resource registering the same error mapping as
 * {@see \SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorMappingRegisteringCartsFixtureResource},
 * declaring another of its codes.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/carts/{cartUuid}/items',
            openapi: new Operation(responses: [
                '201' => new OpenApiResponse(description: 'Added.'),
                '422' => new OpenApiResponse(description: 'Item could not be added.'),
            ]),
            extraProperties: ['declaredErrorCodes' => [422 => ['102']]],
        ),
    ],
    shortName: 'cart-items',
    extraProperties: ['errorMappings' => [['source' => 'CartsConfig::getErrorMapping', 'notAnswered' => ['1508' => 'Raised only by the legacy import.']]]],
)]
class ErrorMappingRegisteringCartItemsFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $sku = null;
}
