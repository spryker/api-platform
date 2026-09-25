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

/**
 * A singleton resource whose identifier exists only so API Platform can mint an IRI for it: its
 * value is the resource type, and the wire carries `data.id: null` as the legacy API did.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/singleton'),
    ],
    shortName: 'singleton',
)]
class SyntheticIdentifierFixtureResource
{
    #[ApiProperty(identifier: true, extraProperties: ['syntheticIdentifier' => true])]
    public ?string $singletonId = null;
}
