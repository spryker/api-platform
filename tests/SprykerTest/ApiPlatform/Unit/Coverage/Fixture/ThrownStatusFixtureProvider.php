<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\State\Provider\AbstractProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ThrownStatusFixtureProvider extends AbstractProvider
{
    public function __construct(protected ThrownStatusFixtureExceptionFactory $exceptionFactory)
    {
    }

    protected function provideItem(): object|null
    {
        throw $this->exceptionFactory->createGoneException();
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *
     * @return array<object>|null
     */
    protected function provideCollection(): array|null
    {
        throw new HttpException(Response::HTTP_NOT_IMPLEMENTED);
    }
}
