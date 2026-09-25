<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

/**
 * A legacy bare-collection route kept only so an old client gets the documented error code rather
 * than a route-not-found. It is hidden from the published document, so `openapi` holds `false` and
 * the statuses it answers ride in `extraProperties` instead.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/hidden',
            openapi: false,
            extraProperties: ['declaredResponses' => [400]],
        ),
    ],
    shortName: 'hidden',
)]
class HiddenDeclaredResponsesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;
}
