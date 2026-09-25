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
use ApiPlatform\Metadata\Post;

/**
 * Carries one property per response-attribute derivation rule: the identifier, a plain readable
 * scalar, a write-only property, an opted-out property, a relationship link and its data sibling,
 * an array declaring its item fields, a bare array that declares none, and a nested object — so
 * the derived path list pins every skip and every path shape in one pass.
 */
#[ApiResource(
    shortName: 'response-attributes-fixture',
    operations: [
        new GetCollection(uriTemplate: '/response-attributes-fixture'),
        new Post(uriTemplate: '/response-attributes-fixture'),
    ],
)]
class ResponseAttributesFixtureResource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    #[ApiProperty(writable: false)]
    public ?string $name = null;

    #[ApiProperty(readable: false)]
    public ?string $secret = null;

    #[ApiProperty(writable: false, extraProperties: ['responseOptional' => true])]
    public ?string $updatedAt = null;

    /**
     * @var array<mixed>
     */
    #[ApiProperty(writable: false, uriTemplate: '/response-attributes-fixture/{id}/items')]
    public array $items = [];

    /**
     * @var array<mixed>
     */
    public array $itemsRelationshipData = [];

    /**
     * @var array<mixed>
     */
    #[ApiProperty(
        writable: false,
        openapiContext: ['items' => ['type' => 'object', 'required' => ['sku', 'quantity'], 'properties' => ['sku' => ['type' => 'string'], 'quantity' => ['type' => 'integer']]]],
    )]
    public array $lines = [];

    /**
     * @var array<mixed>
     */
    #[ApiProperty(writable: false)]
    public array $tags = [];

    #[ApiProperty(writable: false)]
    public ?ResponseAttributesFixturePaginationObject $pagination = null;
}
