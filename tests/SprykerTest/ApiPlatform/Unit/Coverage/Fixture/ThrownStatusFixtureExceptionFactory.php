<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\Exception\GlueApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An exception factory as the Storefront modules write them: a fixed status, a fallback status the
 * caller may override, and a status read from an error mapping at runtime.
 */
class ThrownStatusFixtureExceptionFactory
{
    public function createNotFoundException(): GlueApiException
    {
        return new GlueApiException(Response::HTTP_NOT_FOUND, '101', 'Not found.');
    }

    public function createGoneException(): GlueApiException
    {
        return new GlueApiException(Response::HTTP_GONE, '104', 'Gone.');
    }

    public function createExceptionFromResponse(string $fallbackDetail, int $fallbackStatus = Response::HTTP_UNPROCESSABLE_ENTITY): GlueApiException
    {
        return new GlueApiException($fallbackStatus, '102', $fallbackDetail);
    }

    /**
     * @param array<string, int> $errorMapping
     */
    public function createMappedException(array $errorMapping): GlueApiException
    {
        return new GlueApiException($errorMapping['status'], '103', 'Mapped.');
    }
}
