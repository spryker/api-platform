<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

/**
 * Gives an access denial of an MCP tool to the firewall, which answers with its entry point
 * or with 403, instead of the JSON-RPC error of the MCP transport.
 */
class McpAccessDeniedSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 1024],
        ];
    }

    /**
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getRequest()->attributes->get(RequestAttribute::MCP_ACCESS_DENIED);

        if (!$exception instanceof Throwable) {
            return;
        }

        $event->getRequest()->attributes->remove(RequestAttribute::MCP_ACCESS_DENIED);

        throw new AccessDeniedException($exception->getMessage(), $exception);
    }
}
