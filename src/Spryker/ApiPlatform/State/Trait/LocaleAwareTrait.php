<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Trait;

use Generated\Shared\Transfer\LocaleTransfer;
use Spryker\ApiPlatform\Exception\ApiPlatformContextException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Grants access to the resolved {@see \Generated\Shared\Transfer\LocaleTransfer} for
 * API Platform state classes (providers and processors).
 *
 * The LocaleTransfer is placed on the Symfony Request attributes by
 * {@see \Spryker\ApiPlatform\EventSubscriber\AcceptLanguageLocaleSubscriber::onKernelRequest()}
 * — which is registered for storefront requests only (see its
 * `#[\Spryker\ApiPlatform\Attribute\ApiType(types: ['storefront'])]` annotation). For
 * backend requests the attribute is absent unless another subscriber populates it,
 * which is why the `find*` methods exist alongside the throwing `get*` variants.
 *
 * Requires the host class to provide `hasRequest(): bool` and `getRequest(): \Symfony\Component\HttpFoundation\Request`.
 */
trait LocaleAwareTrait
{
    protected const string ATTRIBUTE_LOCALE_TRANSFER = 'LocaleTransfer';

    abstract protected function hasRequest(): bool;

    abstract protected function getRequest(): Request;

    protected function hasLocale(): bool
    {
        return $this->hasRequest()
            && $this->getRequest()->attributes->get(static::ATTRIBUTE_LOCALE_TRANSFER) !== null;
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\ApiPlatformContextException When the locale
     *     attribute is absent from the request — always guard with {@see self::hasLocale()}
     *     or use {@see self::findLocale()} for nullable access.
     */
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

    /**
     * Nullable access to the resolved LocaleTransfer. Returns null when no request is in
     * context or the locale attribute has not been populated by
     * {@see \Spryker\ApiPlatform\EventSubscriber\AcceptLanguageLocaleSubscriber}.
     */
    protected function findLocale(): ?LocaleTransfer
    {
        return $this->hasLocale() ? $this->getLocale() : null;
    }

    /**
     * Convenience wrapper around {@see self::findLocale()} that returns the resolved
     * locale code (e.g. `de_DE`) or null when no locale is on the request.
     */
    protected function findLocaleName(): ?string
    {
        return $this->findLocale()?->getLocaleName();
    }
}
