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
use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\PaginationTransfer;
use Generated\Shared\Transfer\StoreTransfer;
use Spryker\ApiPlatform\Exception\ApiPlatformContextException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected const string ATTRIBUTE_LOCALE_TRANSFER = 'LocaleTransfer';

    protected const string ATTRIBUTE_STORE_TRANSFER = 'StoreTransfer';

    protected const string QUERY_PARAM_PAGE = 'page';

    protected const string QUERY_PARAM_PER_PAGE = 'perPage';

    protected const int DEFAULT_PAGE = 1;

    protected const int DEFAULT_PER_PAGE = 10;

    protected Operation $operation;

    /**
     * @var array<string, mixed>
     */
    protected array $uriVariables = [];

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

    /**
     * @return array<string, mixed>
     */
    protected function getUriVariables(): array
    {
        return $this->uriVariables;
    }

    protected function hasUriVariable(string $name): bool
    {
        return array_key_exists($name, $this->uriVariables);
    }

    protected function getUriVariable(string $name): mixed
    {
        if (!$this->hasUriVariable($name)) {
            throw new ApiPlatformContextException(sprintf(
                'The uri variable "%s" is missing. Either you have to make sure you call `%s::hasUriVariable()` before or there is a major issue in your setup.',
                $name,
                static::class,
            ));
        }

        return $this->uriVariables[$name];
    }

    protected function hasLocale(): bool
    {
        return $this->hasRequest()
            && $this->getRequest()->attributes->get(static::ATTRIBUTE_LOCALE_TRANSFER) !== null;
    }

    protected function getLocale(): LocaleTransfer
    {
        if (!$this->hasLocale()) {
            throw new ApiPlatformContextException(sprintf(
                'The locale object is missing in the context. Either you have to make sure you call `%s::hasLocale()` before or there is a major issue in your setup.',
                static::class,
            ));
        }

        return $this->getRequest()->attributes->get(static::ATTRIBUTE_LOCALE_TRANSFER);
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

    protected function hasStore(): bool
    {
        return $this->hasRequest()
            && $this->getRequest()->attributes->get(static::ATTRIBUTE_STORE_TRANSFER) !== null;
    }

    protected function getStore(): StoreTransfer
    {
        if (!$this->hasStore()) {
            throw new ApiPlatformContextException(sprintf(
                'The store object is missing in the context. Either you have to make sure you call `%s::hasStore()` before or there is a major issue in your setup.',
                static::class,
            ));
        }

        return $this->getRequest()->attributes->get(static::ATTRIBUTE_STORE_TRANSFER);
    }

    protected function getPagination(): PaginationTransfer
    {
        $request = $this->getRequest();

        return (new PaginationTransfer())
            ->setPage($request->query->getInt(static::QUERY_PARAM_PAGE, static::DEFAULT_PAGE))
            ->setMaxPerPage($request->query->getInt(static::QUERY_PARAM_PER_PAGE, static::DEFAULT_PER_PAGE));
    }
}
