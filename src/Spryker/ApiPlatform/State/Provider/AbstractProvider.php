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
     *
     * @return object|null
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

    protected function getPagination(): PaginationTransfer
    {
        $request = $this->getRequest();

        return (new PaginationTransfer())
            ->setPage($request->query->getInt(static::QUERY_PARAM_PAGE, static::DEFAULT_PAGE))
            ->setMaxPerPage($request->query->getInt(static::QUERY_PARAM_PER_PAGE, static::DEFAULT_PER_PAGE));
    }
}
