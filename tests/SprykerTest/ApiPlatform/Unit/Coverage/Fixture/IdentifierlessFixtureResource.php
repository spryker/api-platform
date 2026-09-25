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
use ApiPlatform\OpenApi\Model\Response;

/**
 * A resource marking no property `identifier: true` — the shape an action endpoint takes, and the
 * one whose responses are outside the envelope's identifier guarantee.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/identifierless',
            openapi: new Operation(
                tags: ['identifierless'],
                responses: [
                    200 => new Response(description: 'Accepted.'),
                ],
            ),
        ),
    ],
    shortName: 'identifierless',
)]
class IdentifierlessFixtureResource
{
    #[ApiProperty(description: 'Carries no identity of its own.')]
    public ?string $outcome = null;
}
