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
use ApiPlatform\Metadata\Post;
use DateTimeImmutable;

/**
 * One property per way an attribute counts as typed - an enum-like name, a date-like name, a date
 * type, an OpenAPI enum - next to an untyped one and a read-only one.
 */
#[ApiResource(
    shortName: 'typed-request-attributes-fixture',
    operations: [
        new Post(uriTemplate: '/typed-request-attributes-fixture'),
        new Patch(uriTemplate: '/typed-request-attributes-fixture/{uuid}'),
    ],
)]
class TypedRequestAttributesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;

    public ?string $name = null;

    public ?string $priceMode = null;

    public ?string $dateOfBirth = null;

    public ?DateTimeImmutable $deliveryWindowStart = null;

    #[ApiProperty(openapiContext: ['enum' => ['Mr', 'Mrs']])]
    public ?string $salutation = null;

    #[ApiProperty(writable: false)]
    public ?string $currency = null;
}
