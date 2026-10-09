<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ThrownStatusFixtureArgumentRequiredHttpException extends HttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(Response::HTTP_LOCKED, $reason);
    }
}
