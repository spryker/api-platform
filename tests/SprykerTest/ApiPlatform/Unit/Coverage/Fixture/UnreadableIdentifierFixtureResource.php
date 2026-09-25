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
use ApiPlatform\OpenApi\Model\Response;

/**
 * A resource whose identifier is marked `readable: false`, which keeps the value out of the
 * `attributes` object while `data.id` still carries it. The shape most resources here take, and the
 * one that must stay inside the envelope's identifier guarantee.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/unreadable-identifier/{uuid}',
            openapi: new Operation(
                tags: ['unreadable-identifier'],
                responses: [
                    200 => new Response(description: 'A single record.'),
                ],
            ),
        ),
    ],
    shortName: 'unreadable-identifier',
)]
class UnreadableIdentifierFixtureResource
{
    #[ApiProperty(
        description: 'Answered as data.id, not repeated inside attributes.',
        writable: false,
        readable: false,
        identifier: true,
    )]
    public ?string $uuid = null;

    #[ApiProperty(description: 'Record name.')]
    public ?string $name = null;
}
