<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Provider;

use BadMethodCallException;
use Generated\Shared\Transfer\CustomerTransfer;
use Generated\Shared\Transfer\FilterTransfer;
use Spryker\ApiPlatform\Request\RequestAttribute;

abstract class AbstractStorefrontProvider extends AbstractProvider
{
    public const string ATTRIBUTE_CUSTOMER_TRANSFER = RequestAttribute::CUSTOMER_TRANSFER;

    protected const string SEARCH_PARAMETER_PAGE = 'page';

    protected const string SEARCH_PARAMETER_ITEMS_PER_PAGE = 'ipp';

    protected function hasCustomer(): bool
    {
        return $this->getRequest()->attributes->get(static::ATTRIBUTE_CUSTOMER_TRANSFER) !== null;
    }

    protected function getCustomer(): CustomerTransfer
    {
        if (!$this->hasCustomer()) {
            throw new BadMethodCallException(sprintf(
                'No CustomerTransfer given. You need to make sure that you call `%s::hasCustomer()` before you call getCustomer().',
                static::class,
            ));
        }

        return $this->getRequest()->attributes->get(static::ATTRIBUTE_CUSTOMER_TRANSFER);
    }

    protected function isGuestCustomer(): bool
    {
        return $this->hasCustomer() && $this->getCustomer()->getIsGuest() === true;
    }

    protected function getCustomerReference(): string
    {
        return $this->getCustomer()->getCustomerReferenceOrFail();
    }

    /**
     * Builds a {@see FilterTransfer} pre-populated with `?page[limit]` and `?page[offset]`
     * from the current request. Convenience helper for legacy-style readers that accept a
     * `FilterTransfer` with limit/offset (e.g.
     * `$collectionRequest->setFilter($this->buildFilterTransfer())`).
     */
    protected function buildFilterTransfer(int $limit = self::DEFAULT_PER_PAGE, int $offset = self::DEFAULT_OFFSET): FilterTransfer
    {
        return (new FilterTransfer())
            ->setLimit($this->getPaginationLimit($limit))
            ->setOffset($this->getPaginationOffset($offset));
    }

    /**
     * Translates JSON:API `?page[limit]` / `?page[offset]` into Spryker search-style
     * `page` (1-based) + `ipp` (items per page) keys, falling back to `$defaultItemsPerPage`
     * when the limit is not provided. Used for Search API request params, e.g.
     * `$searchRequest->setRequestParams($this->buildSearchPaginationRequestParams(10))`.
     *
     * @return array<string, int>
     */
    protected function buildSearchPaginationRequestParams(int $defaultItemsPerPage): array
    {
        $itemsPerPage = $this->getPaginationLimit($defaultItemsPerPage);
        $offset = $this->getPaginationOffset();

        $page = $itemsPerPage > 0
            ? (int)floor($offset / $itemsPerPage) + static::DEFAULT_PAGE
            : static::DEFAULT_PAGE;

        return [
            static::SEARCH_PARAMETER_PAGE => $page,
            static::SEARCH_PARAMETER_ITEMS_PER_PAGE => $itemsPerPage,
        ];
    }
}
