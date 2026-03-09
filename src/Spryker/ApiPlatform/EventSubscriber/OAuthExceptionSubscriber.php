<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts {@link OAuthServerException} into a Symfony {@link HttpException} so that
 * API Platform can handle it through its normal error rendering pipeline.
 *
 * Without this, unhandled OAuthServerException instances would bubble up as
 * generic 500 Internal Server Error responses instead of the appropriate HTTP status codes.
 */
#[\Spryker\ApiPlatform\Attribute\ApiType(types: ['storefront'])]
class OAuthExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof OAuthServerException) {
            return;
        }

        $event->setThrowable(new HttpException(
            $exception->getHttpStatusCode(),
            $exception->getMessage(),
            $exception,
        ));
    }
}
