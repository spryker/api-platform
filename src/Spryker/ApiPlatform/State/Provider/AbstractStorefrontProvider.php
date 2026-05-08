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
use Generated\Shared\Transfer\PaginationTransfer;

abstract class AbstractStorefrontProvider extends AbstractProvider
{
    public const string ATTRIBUTE_CUSTOMER_TRANSFER = 'CustomerTransfer';

    protected const string QUERY_PARAMETER_PAGE = 'page';

    protected const string QUERY_PARAMETER_LIMIT = 'limit';

    protected const string QUERY_PARAMETER_OFFSET = 'offset';

    protected const string SEARCH_PARAMETER_PAGE = 'page';

    protected const string SEARCH_PARAMETER_ITEMS_PER_PAGE = 'ipp';

    protected const int DEFAULT_PAGE_NUMBER = 1;

    protected const int DEFAULT_OFFSET = 0;

    protected const int DEFAULT_LIMIT = 10;

    protected const string PAGINATION_KEY_NUM_FOUND = 'numFound';

    protected const string PAGINATION_KEY_CURRENT_PAGE = 'currentPage';

    protected const string PAGINATION_KEY_MAX_PAGE = 'maxPage';

    protected const string PAGINATION_KEY_CURRENT_ITEMS_PER_PAGE = 'currentItemsPerPage';

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
     * Returns the JSON:API `?page[limit]=N` value from the current request, or the default when
     * the parameter is not present. Used to push the page size onto data-layer transfers
     * (`FilterTransfer`, `PaginationTransfer`, etc.) so the SQL fetch is bounded.
     *
     *  Users can also overwrite the limit by passing it as argument to this method.
     */
    protected function getPaginationLimit(int $limit = self::DEFAULT_LIMIT): int
    {
        return $this->getPaginationParameter(static::QUERY_PARAMETER_LIMIT) ?? $this->getOperation()->getPaginationItemsPerPage() ?? $limit;
    }

    /**
     * Returns the JSON:API `?page[offset]=N` value from the current request, or the default when
     * the parameter is not present. Used to push the page offset onto data-layer transfers.
     *
     * Users can also overwrite the offset by passing it as argument to this method.
     */
    protected function getPaginationOffset(int $offset = self::DEFAULT_OFFSET): int
    {
        return $this->getPaginationParameter(static::QUERY_PARAMETER_OFFSET) ?? $offset;
    }

    /**
     * Builds a {@see PaginationTransfer} pre-populated with `?page[limit]` and `?page[offset]`
     * from the current request. Convenience helper for criteria-style facades/clients that
     * accept a `PaginationTransfer` (e.g. `$criteria->setPagination($this->buildPaginationTransfer())`).
     */
    protected function buildPaginationTransfer(int $limit = self::DEFAULT_LIMIT, int $offset = self::DEFAULT_OFFSET): PaginationTransfer
    {
        return (new PaginationTransfer())
            ->setLimit($this->getPaginationLimit($limit))
            ->setOffset($this->getPaginationOffset($offset));
    }

    /**
     * Builds a {@see FilterTransfer} pre-populated with `?page[limit]` and `?page[offset]`
     * from the current request. Convenience helper for legacy-style readers that accept a
     * `FilterTransfer` with limit/offset (e.g.
     * `$collectionRequest->setFilter($this->buildFilterTransfer())`).
     */
    protected function buildFilterTransfer(int $limit = self::DEFAULT_LIMIT, int $offset = self::DEFAULT_OFFSET): FilterTransfer
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
            ? (int)floor($offset / $itemsPerPage) + static::DEFAULT_PAGE_NUMBER
            : static::DEFAULT_PAGE_NUMBER;

        return [
            static::SEARCH_PARAMETER_PAGE => $page,
            static::SEARCH_PARAMETER_ITEMS_PER_PAGE => $itemsPerPage,
        ];
    }

    /**
     * Builds the Spryker-style pagination wrapper consumed by
     * {@see \Spryker\ApiPlatform\EventSubscriber\PaginationLinksResponseSubscriber} to emit
     * JSON:API top-level pagination links (first/last/prev/next). Used by collection providers
     * that need an embedded `pagination` property on the resource — e.g.
     * `$resource->pagination = $this->calculatePagination($offset, $limit, $nbResults)`.
     *
     * @return array<string, int>
     */
    protected function calculatePagination(int $offset, int $limit, int $nbResults): array
    {
        $maxPage = $limit > 0 ? (int)ceil($nbResults / $limit) : static::DEFAULT_PAGE_NUMBER;
        $currentPage = $limit > 0
            ? (int)floor($offset / $limit) + static::DEFAULT_PAGE_NUMBER
            : static::DEFAULT_PAGE_NUMBER;

        return [
            static::PAGINATION_KEY_NUM_FOUND => $nbResults,
            static::PAGINATION_KEY_CURRENT_PAGE => $currentPage,
            static::PAGINATION_KEY_MAX_PAGE => $maxPage,
            static::PAGINATION_KEY_CURRENT_ITEMS_PER_PAGE => $limit,
        ];
    }

    protected function getPaginationParameter(string $name): ?int
    {
        if (!$this->hasRequest()) {
            return null;
        }

        $page = $this->getRequest()->query->all()[static::QUERY_PARAMETER_PAGE] ?? [];

        if (!is_array($page) || !isset($page[$name])) {
            return null;
        }

        return (int)$page[$name];
    }
}
