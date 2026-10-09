<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use Spryker\ApiPlatform\State\Processor\AbstractProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Throws a different status from each operation method, through every path the analyzer follows.
 */
class ThrownStatusFixtureProcessor extends AbstractProcessor
{
    public function __construct(protected ThrownStatusFixtureExceptionFactory $exceptionFactory)
    {
    }

    protected function processPost(mixed $data): mixed
    {
        if ($data === null) {
            throw $this->exceptionFactory->createExceptionFromResponse('Not created.');
        }

        return $data;
    }

    protected function processPatch(mixed $data): mixed
    {
        $this->assertUuid();

        if ($data === null) {
            throw $this->exceptionFactory->createExceptionFromResponse('Not updated.', Response::HTTP_BAD_REQUEST);
        }

        return $data;
    }

    protected function processDelete(): mixed
    {
        if (!$this->hasRequest()) {
            throw new AccessDeniedException();
        }

        throw new NotFoundHttpException();
    }

    protected function assertUuid(): void
    {
        if ($this->getUriVariables() === []) {
            throw $this->exceptionFactory->createNotFoundException();
        }
    }

    protected function throwMappedException(): void
    {
        throw $this->exceptionFactory->createMappedException(['status' => Response::HTTP_CONFLICT]);
    }

    protected function throwArgumentRequiredException(): void
    {
        throw new ThrownStatusFixtureArgumentRequiredHttpException('Locked.');
    }
}
