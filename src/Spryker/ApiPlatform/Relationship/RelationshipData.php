<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Relationship;

/**
 * Wrapper for relationship resources with optional pagination metadata.
 */
class RelationshipData
{
    /**
     * @param array<object> $resources Related resources
     * @param \Spryker\ApiPlatform\Relationship\RelationshipPagination|null $pagination Pagination metadata
     */
    public function __construct(
        public readonly array $resources,
        public readonly ?RelationshipPagination $pagination = null,
    ) {
    }

    public function isPaginated(): bool
    {
        return $this->pagination !== null;
    }

    /**
     * @return array<object>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    public function getPagination(): ?RelationshipPagination
    {
        return $this->pagination;
    }
}
