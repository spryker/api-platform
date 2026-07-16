<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Relationship;

use Generated\Shared\Transfer\CustomerTransfer;
use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\StoreTransfer;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractRelationshipResolver implements RelationshipResolverInterface
{
    protected const string ATTRIBUTE_LOCALE_TRANSFER = 'LocaleTransfer';

    protected const string ATTRIBUTE_STORE_TRANSFER = 'StoreTransfer';

    protected const string ATTRIBUTE_CUSTOMER_TRANSFER = 'CustomerTransfer';

    /**
     * @var array<object>
     */
    protected array $parentResources = [];

    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * {@inheritDoc}
     *
     * @param array<object> $parentResources
     * @param array<string, mixed> $context
     */
    public function resolve(array $parentResources, array $context): array
    {
        $this->parentResources = $parentResources;
        $this->context = $context;

        return $this->resolveRelationship();
    }

    /**
     * Resolves related resources for the stored parent resources.
     *
     * @return array<object>
     */
    abstract protected function resolveRelationship(): array;

    /**
     * @return array<object>
     */
    protected function getParentResources(): array
    {
        return $this->parentResources;
    }

    protected function hasRequest(): bool
    {
        return isset($this->context['request']);
    }

    protected function getRequest(): Request
    {
        return $this->context['request'];
    }

    protected function hasLocale(): bool
    {
        return $this->hasRequest()
            && $this->getRequest()->attributes->get(static::ATTRIBUTE_LOCALE_TRANSFER) !== null;
    }

    protected function getLocale(): LocaleTransfer
    {
        return $this->getRequest()->attributes->get(static::ATTRIBUTE_LOCALE_TRANSFER);
    }

    protected function hasStore(): bool
    {
        return $this->hasRequest()
            && $this->getRequest()->attributes->get(static::ATTRIBUTE_STORE_TRANSFER) !== null;
    }

    protected function getStore(): StoreTransfer
    {
        return $this->getRequest()->attributes->get(static::ATTRIBUTE_STORE_TRANSFER);
    }

    protected function hasCustomer(): bool
    {
        return $this->getRequest()->attributes->get(static::ATTRIBUTE_CUSTOMER_TRANSFER) !== null;
    }

    protected function getCustomer(): CustomerTransfer
    {
        return $this->getRequest()->attributes->get(static::ATTRIBUTE_CUSTOMER_TRANSFER);
    }

    protected function getCustomerReference(): string
    {
        return $this->getCustomer()->getCustomerReferenceOrFail();
    }
}
