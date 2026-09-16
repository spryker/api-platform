<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProviderInterface;
use BadMethodCallException;
use Generated\Shared\Transfer\PaginationTransfer;
use Spryker\ApiPlatform\Exception\ApiPlatformContextException;
use Spryker\ApiPlatform\ResponseTransform\PaginationLinksTransform;
use Spryker\ApiPlatform\State\Trait\LocaleAwareTrait;
use Spryker\ApiPlatform\State\Trait\StoreAwareTrait;
use Spryker\ApiPlatform\State\Trait\UriVariableAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
abstract class AbstractProvider implements ProviderInterface
{
    use LocaleAwareTrait;
    use StoreAwareTrait;
    use UriVariableAwareTrait;

    protected const string QUERY_PARAM_PAGE = 'page';

    protected const string QUERY_PARAM_PER_PAGE = 'perPage';

    protected const int DEFAULT_PAGE = 1;

    protected const int DEFAULT_PER_PAGE = 10;

    protected const string QUERY_PARAMETER_LIMIT = 'limit';

    protected const string QUERY_PARAMETER_OFFSET = 'offset';

    protected const int DEFAULT_OFFSET = 0;

    protected const string PAGINATION_KEY_NUM_FOUND = 'numFound';

    protected const string PAGINATION_KEY_CURRENT_PAGE = 'currentPage';

    protected const string PAGINATION_KEY_MAX_PAGE = 'maxPage';

    protected const string PAGINATION_KEY_CURRENT_ITEMS_PER_PAGE = 'currentItemsPerPage';

    protected Operation $operation;

    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $this->operation = $operation;
        $this->uriVariables = $uriVariables;
        $this->context = $context;

        if ($operation instanceof GetCollection) {
            return $this->provideCollection();
        }

        // POST creates a new resource — the provider should not attempt to load an existing item.
        // The processor handles POST logic; the provider returns null to let it proceed.
        if ($operation instanceof Post) {
            return null;
        }

        return $this->provideItem();
    }

    /**
     * @throws \BadMethodCallException
     */
    protected function provideItem(): object|null
    {
        throw new BadMethodCallException(sprintf(
            '%s receives a Get operation but does not implement provideItem(). '
            . 'Override the provideItem() method in %s to load a single resource.',
            static::class,
            static::class,
        ));
    }

    /**
     * @throws \BadMethodCallException
     *
     * @return array<object>|null
     */
    protected function provideCollection(): array|null
    {
        throw new BadMethodCallException(sprintf(
            '%s receives a GetCollection operation but does not implement provideCollection(). '
            . 'Override the provideCollection() method in %s to load a resource collection.',
            static::class,
            static::class,
        ));
    }

    protected function getOperation(): Operation
    {
        return $this->operation;
    }

    protected function hasRequest(): bool
    {
        return isset($this->context['request']);
    }

    protected function getRequest(): Request
    {
        if (!$this->hasRequest()) {
            throw new ApiPlatformContextException(sprintf(
                'The request object is missing in the context. Either you have to make sure you call `%s::hasRequest()` before or there is a major issue in your setup.',
                static::class,
            ));
        }

        return $this->context['request'];
    }

    protected function getPaginationLimit(int $limit = self::DEFAULT_PER_PAGE): int
    {
        $defaultLimit = $this->getOperation()->getPaginationItemsPerPage() ?? $limit;
        $resolvedLimit = $this->getPaginationParameter(static::QUERY_PARAMETER_LIMIT) ?? $defaultLimit;
        $maximumLimit = $this->getOperation()->getPaginationMaximumItemsPerPage();

        if ($resolvedLimit < 1) {
            $resolvedLimit = $defaultLimit;
        }

        return $maximumLimit === null ? $resolvedLimit : min($resolvedLimit, $maximumLimit);
    }

    protected function getPaginationOffset(int $offset = self::DEFAULT_OFFSET): int
    {
        return max($this->getPaginationParameter(static::QUERY_PARAMETER_OFFSET) ?? $offset, static::DEFAULT_OFFSET);
    }

    protected function buildPaginationTransfer(int $limit = self::DEFAULT_PER_PAGE, int $offset = self::DEFAULT_OFFSET): PaginationTransfer
    {
        $resolvedLimit = $this->getPaginationLimit($limit);
        $resolvedOffset = $this->getPaginationOffset($offset);

        return (new PaginationTransfer())
            ->setLimit($resolvedLimit)
            ->setOffset($resolvedOffset)
            ->setMaxPerPage($resolvedLimit)
            ->setPage(intdiv($resolvedOffset, max($resolvedLimit, 1)) + 1);
    }

    protected function setCollectionPagination(int $offset, int $limit, int $nbResults): void
    {
        if (!$this->hasRequest()) {
            return;
        }

        $this->getRequest()->attributes->set(
            PaginationLinksTransform::REQUEST_ATTRIBUTE_PAGINATION,
            $this->calculatePagination($offset, $limit, $nbResults),
        );
    }

    /**
     * Spryker-style pagination array: numFound, currentPage, maxPage, currentItemsPerPage.
     *
     * @return array<string, int>
     */
    protected function calculatePagination(int $offset, int $limit, int $nbResults): array
    {
        $maxPage = $limit > 0 ? (int)ceil($nbResults / $limit) : static::DEFAULT_PAGE;

        $requestedPage = static::DEFAULT_PAGE;

        if ($limit > 0) {
            $requestedPage = (int)floor($offset / $limit) + static::DEFAULT_PAGE;
        }

        $lastPage = max($maxPage, static::DEFAULT_PAGE);
        $currentPage = min($requestedPage, $lastPage);

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

        $page = $this->getRequest()->query->all()[static::QUERY_PARAM_PAGE] ?? [];

        if (!is_array($page) || !isset($page[$name])) {
            return null;
        }

        return (int)$page[$name];
    }

    protected function getPagination(): PaginationTransfer
    {
        $request = $this->getRequest();

        return (new PaginationTransfer())
            ->setPage($request->query->getInt(static::QUERY_PARAM_PAGE, static::DEFAULT_PAGE))
            ->setMaxPerPage($request->query->getInt(static::QUERY_PARAM_PER_PAGE, static::DEFAULT_PER_PAGE));
    }
}
