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
use ApiPlatform\Symfony\Action\NotFoundAction;

/**
 * The two shapes a providerless item `Get` can have: one that forgot its provider, and one declared
 * unreachable on purpose so it can still mint IRIs for its sub-resources.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/unservable/{uuid}',
        ),
        new Get(
            uriTemplate: '/unservable/{uuid}/internal',
            controller: NotFoundAction::class,
            output: false,
            read: false,
            openapi: false,
            extraProperties: ['internal' => true],
        ),
    ],
    shortName: 'unservable',
)]
class UnservableItemGetFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;
}
