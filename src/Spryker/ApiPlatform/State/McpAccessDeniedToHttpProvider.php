<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State;

use ApiPlatform\Metadata\Exception\AccessDeniedException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;

/**
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
class McpAccessDeniedToHttpProvider implements ProviderInterface
{
    /**
     * @param \ApiPlatform\State\ProviderInterface<object> $decorated
     */
    public function __construct(
        protected ProviderInterface $decorated,
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
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        try {
            return $this->decorated->provide($operation, $uriVariables, $context);
        } catch (AccessDeniedException | SecurityAccessDeniedException $exception) {
            $this->requestStack->getMainRequest()?->attributes->set(RequestAttribute::MCP_ACCESS_DENIED, $exception);

            throw $exception;
        }
    }
}
