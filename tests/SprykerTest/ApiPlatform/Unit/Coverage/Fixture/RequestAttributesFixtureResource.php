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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Optional;

/**
 * One property per request-attribute derivation rule: a writable scalar, a read-only one, the
 * identifier, one writable only on create, a nested value object, a collection with an optional
 * field and a deeper collection, and a list of collections.
 */
#[ApiResource(
    shortName: 'request-attributes-fixture',
    operations: [
        new Get(uriTemplate: '/request-attributes-fixture/{uuid}'),
        new Post(uriTemplate: '/request-attributes-fixture'),
        new Patch(uriTemplate: '/request-attributes-fixture/{uuid}'),
    ],
    provider: 'request-attributes-provider',
)]
class RequestAttributesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $uuid = null;

    public ?string $name = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(extraProperties: ['writableOn' => ['Post']])]
    public ?string $store = null;

    public ?RequestAttributesFixtureSalesUnitObject $salesUnit = null;

    #[Collection(fields: [
        'isComplete' => [new NotNull()],
        'displayData' => new Optional([new NotBlank()]),
        'prices' => new Optional([new All(constraints: [new Collection(fields: ['currency' => [new Collection(fields: ['code' => [new NotBlank()]])]])])]),
    ])]
    public mixed $configuration = null;

    /**
     * @var array<mixed>
     */
    #[All(constraints: [new Collection(fields: ['items' => [new NotBlank()], 'requestedDeliveryDate' => new Optional([new Date()])])])]
    public array $shipments = [];
}
