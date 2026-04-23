<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Processor;

use Generated\Shared\Transfer\UserTransfer;

abstract class AbstractBackendProcessor extends AbstractProcessor
{
    protected const string ATTRIBUTE_USER_TRANSFER = 'UserTransfer';

    protected function hasUser(): bool
    {
        return $this->getRequest()->attributes->get(static::ATTRIBUTE_USER_TRANSFER) !== null;
    }

    protected function getUser(): UserTransfer
    {
        return $this->getRequest()->attributes->get(static::ATTRIBUTE_USER_TRANSFER);
    }
}
