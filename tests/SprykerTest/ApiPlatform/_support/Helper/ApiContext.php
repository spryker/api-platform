<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Generated\Shared\Transfer\CustomerTransfer;
use Spryker\ApiPlatform\State\Provider\AbstractStorefrontProvider;
use Symfony\Component\HttpFoundation\Request;

class ApiContext
{
    protected array $context = [];

    /**
     * Every context must have a request otherwise we throw an exception.
     *
     * In case you need to have a context without request you can pass `$seedData['request'] = null;`
     */
    public function getContext(array $seedData = []): self
    {
        // Every context must have a request otherwise we throw an exception. In case you need to have a context without request
        if (!isset($seedData['request'])) {
            $seedData['request'] = new Request();
        }

        if (($seedData['request'] === null)) {
            unset($seedData['request']);
        }

        $this->context = $seedData;

        return $this;
    }

    public function withCustomer(CustomerTransfer $customerTransfer): self
    {
        if (!$this->context || !isset($this->context['request'])) {
            $this->getContext();
        }

        /** @var \Symfony\Component\HttpFoundation\Request $request */
        $request = $this->context['request'];
        $request->attributes->set(AbstractStorefrontProvider::ATTRIBUTE_CUSTOMER_TRANSFER, $customerTransfer);

        return $this;
    }

    public function withRouteParams(array $routeParams = []): self
    {
        if (!$this->context || !isset($this->context['request'])) {
            $this->getContext();
        }

        /** @var \Symfony\Component\HttpFoundation\Request $request */
        $request = $this->context['request'];
        $request->attributes->set('_route_params', $routeParams);

        return $this;
    }

    public function toArray(): array
    {
        return $this->context;
    }
}
