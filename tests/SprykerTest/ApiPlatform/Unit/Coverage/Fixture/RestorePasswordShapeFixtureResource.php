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
 * An item operation that deliberately declares no 404 — the `customer-restore-password` case, where
 * answering 404 for an unknown key would be a restore-key oracle.
 */
#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/customer-restore-password/{restorePasswordKey}',
            openapi: new Operation(
                responses: [
                    '204' => new OpenApiResponse(description: 'Password restored.'),
                    '422' => new OpenApiResponse(description: 'The restore key is invalid.'),
                ],
            ),
        ),
    ],
    shortName: 'customer-restore-password',
)]
class RestorePasswordShapeFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $restorePasswordKey = null;
}
