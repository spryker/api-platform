<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage\Fixture;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements \ApiPlatform\State\ProcessorInterface<mixed, mixed>
 */
class SelfDispatchingThrownStatusFixtureProcessor implements ProcessorInterface
{
    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        throw new GlueApiException($data === null ? Response::HTTP_CONFLICT : Response::HTTP_GONE);
    }
}
