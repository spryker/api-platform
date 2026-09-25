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
 * Mirrors the generated shape of a resource whose operation declares a success and two error
 * responses — the `customer-password` case, where the declared 403 must not be remapped to a 404.
 */
#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/customer-password/{customerReference}',
            openapi: new Operation(
                tags: ['customer-password'],
                responses: [
                    '204' => new OpenApiResponse(description: 'Password updated successfully.'),
                    '403' => new OpenApiResponse(description: 'Access denied.'),
                    '404' => new OpenApiResponse(description: 'Customer not found.'),
                ],
            ),
        ),
    ],
    shortName: 'customer-password',
)]
class DeclaredResponsesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $customerReference = null;
}
