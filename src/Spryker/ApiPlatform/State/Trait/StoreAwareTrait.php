<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Trait;

use Generated\Shared\Transfer\StoreTransfer;
use Spryker\ApiPlatform\Exception\ApiPlatformContextException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Grants access to the resolved {@see \Generated\Shared\Transfer\StoreTransfer} for
 * API Platform state classes (providers and processors).
 *
 * The StoreTransfer is placed on the Symfony Request attributes by
 * {@see \Spryker\ApiPlatform\EventSubscriber\AcceptLanguageLocaleSubscriber::onKernelRequest()}
 * via `\Spryker\Client\Store\StoreClientInterface::getCurrentStore()` before locale
 * resolution. The subscriber is registered for storefront requests only (see its
 * `#[\Spryker\ApiPlatform\Attribute\ApiType(types: ['storefront'])]` annotation). For
 * backend requests the attribute is absent unless another subscriber populates it,
 * which is why the `find*` methods exist alongside the throwing `get*` variants.
 *
 * Requires the host class to provide `hasRequest(): bool` and `getRequest(): \Symfony\Component\HttpFoundation\Request`.
 */
trait StoreAwareTrait
{
    protected const string ATTRIBUTE_STORE_TRANSFER = 'StoreTransfer';

    abstract protected function hasRequest(): bool;

    abstract protected function getRequest(): Request;

    protected function hasStore(): bool
    {
        return $this->hasRequest()
            && $this->getRequest()->attributes->get(static::ATTRIBUTE_STORE_TRANSFER) !== null;
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\ApiPlatformContextException When the store
     *     attribute is absent from the request — always guard with {@see self::hasStore()}
     *     or use {@see self::findStore()} for nullable access.
     */
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

    /**
     * Nullable access to the resolved StoreTransfer. Returns null when no request is in
     * context or the store attribute has not been populated by
     * {@see \Spryker\ApiPlatform\EventSubscriber\AcceptLanguageLocaleSubscriber}.
     */
    protected function findStore(): ?StoreTransfer
    {
        return $this->hasStore() ? $this->getStore() : null;
    }

    /**
     * Convenience wrapper around {@see self::findStore()} that returns the current
     * store name (e.g. `DE`) or null when no store is on the request.
     */
    protected function findStoreName(): ?string
    {
        return $this->findStore()?->getName();
    }
}
