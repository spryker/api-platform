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

/**
 * A resource whose schema declares no responses at all — the defect the gate has to name.
 */
#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/undeclared/{uuid}',
            openapi: new Operation(
                tags: ['undeclared'],
            ),
        ),
    ],
    shortName: 'undeclared',
)]
class UndeclaredResponsesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;
}
