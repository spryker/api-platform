<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Processor;

use Generated\Shared\Transfer\CustomerTransfer;

abstract class AbstractStorefrontProcessor extends AbstractProcessor
{
    public const string ATTRIBUTE_CUSTOMER_TRANSFER = 'CustomerTransfer';

    protected function hasCustomer(): bool
    {
        return $this->getRequest()->attributes->get(static::ATTRIBUTE_CUSTOMER_TRANSFER) !== null;
    }

    protected function getCustomer(): CustomerTransfer
    {
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
}
