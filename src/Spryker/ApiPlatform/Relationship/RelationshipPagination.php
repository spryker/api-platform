<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Relationship;

/**
 * Value object representing pagination metadata for a relationship.
 */
class RelationshipPagination
{
    /**
     * @param int $currentPage Current page number (1-based)
     * @param int $itemsPerPage Number of items per page
     * @param int $totalItems Total number of items across all pages
     * @param int $totalPages Total number of pages
     */
    public function __construct(
        public readonly int $currentPage,
        public readonly int $itemsPerPage,
        public readonly int $totalItems,
        public readonly int $totalPages,
    ) {
    }

    /**
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * @return bool
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * @return int
     */
    public function getNextPage(): int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : $this->currentPage;
    }

    /**
     * @return int
     */
    public function getPreviousPage(): int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : $this->currentPage;
    }
}
