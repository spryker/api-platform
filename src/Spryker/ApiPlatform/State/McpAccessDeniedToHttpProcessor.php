<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State;

use ApiPlatform\Metadata\Exception\AccessDeniedException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;

/**
 * @implements \ApiPlatform\State\ProcessorInterface<mixed, mixed>
 */
class McpAccessDeniedToHttpProcessor implements ProcessorInterface
{
    /**
     * @param \ApiPlatform\State\ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        protected ProcessorInterface $decorated,
        protected RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws \ApiPlatform\Metadata\Exception\AccessDeniedException
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        try {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        } catch (AccessDeniedException | SecurityAccessDeniedException $exception) {
            $this->requestStack->getMainRequest()?->attributes->set(RequestAttribute::MCP_ACCESS_DENIED, $exception);

            throw $exception;
        }
    }
}
