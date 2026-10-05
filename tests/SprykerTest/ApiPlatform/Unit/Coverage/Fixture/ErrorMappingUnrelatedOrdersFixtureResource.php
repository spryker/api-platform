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
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;

/**
 * A resource declaring code 118 without registering the error mapping that carries it.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/orders/{orderReference}',
            openapi: new Operation(responses: [
                '200' => new OpenApiResponse(description: 'Found.'),
                '422' => new OpenApiResponse(description: 'Order could not be read.'),
            ]),
            extraProperties: ['declaredErrorCodes' => [422 => ['118']]],
        ),
    ],
    shortName: 'orders',
)]
class ErrorMappingUnrelatedOrdersFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $orderReference = null;
}
