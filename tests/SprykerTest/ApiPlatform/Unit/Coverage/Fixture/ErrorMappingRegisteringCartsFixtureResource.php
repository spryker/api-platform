<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;

/**
 * Mirrors the generated shape of a resource that registers an error mapping and declares one of
 * its codes, with a code the API never answers.
 */
#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/carts/{cartUuid}',
            openapi: new Operation(responses: [
                '200' => new OpenApiResponse(description: 'Updated.'),
                '422' => new OpenApiResponse(description: 'Cart could not be updated.'),
            ]),
            extraProperties: ['declaredErrorCodes' => [422 => ['101']]],
        ),
    ],
    shortName: 'carts',
    extraProperties: ['errorMappings' => [['source' => 'CartsConfig::getErrorMapping', 'notAnswered' => ['1507' => 'Raised only by the legacy merge.']]]],
)]
class ErrorMappingRegisteringCartsFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $cartUuid = null;
}
