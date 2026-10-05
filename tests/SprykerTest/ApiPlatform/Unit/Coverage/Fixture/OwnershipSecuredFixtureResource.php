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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;

/**
 * A resource guarded by an ownership voter: its item GET inherits the resource's expression, its
 * PATCH states its own and keeps the ownership check, and its collection overrides it with a role
 * check only.
 */
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/customers/{customerReference}/notes/{uuid}'),
        new Patch(
            uriTemplate: '/customers/{customerReference}/notes/{uuid}',
            security: "is_granted('ROLE_CUSTOMER') and is_granted('CUSTOMER_OWNER', request.attributes.get('customerReference'))",
        ),
        new GetCollection(uriTemplate: '/notes', security: "is_granted('ROLE_CUSTOMER')"),
    ],
    shortName: 'notes',
    security: "is_granted('ROLE_CUSTOMER') and is_granted('CUSTOMER_OWNER')",
    provider: 'notes-provider',
)]
class OwnershipSecuredFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;
}
