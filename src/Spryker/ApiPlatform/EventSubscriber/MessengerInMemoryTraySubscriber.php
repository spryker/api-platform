<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use Spryker\Shared\Messenger\MessengerConfig as SharedMessengerConfig;
use Spryker\Zed\Messenger\MessengerConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Points the messenger at the in-memory tray for the duration of an API request.
 *
 * {@see \Spryker\Zed\Messenger\MessengerConfig::getTray()} chooses the tray by SAPI and treats
 * everything that is not CLI as having a session. That holds for Zed and the Back Office, but an
 * API application runs under FPM and is stateless, so the session tray's
 * `$requestStack->getCurrentRequest()->getSession()` raises `SessionNotFoundException`.
 *
 * Without this, any code reached from an API request that reports through the messenger replaces
 * its own outcome with "Session has not been set." — and does so *after* the work it was reporting
 * on was committed. {@see \Spryker\Zed\Mail\Business\Model\Mailer\MailHandler::handleMailSendFailure()}
 * is the common way in: it notifies when a mail transport fails, which turned a registered customer
 * into a 422 that named the wrong subsystem.
 *
 * {@see \Spryker\Zed\ZedRequest\Communication\Plugin\GatewayControllerListenerPlugin} does the same
 * for the Zed gateway, the other session-less entry point.
 */
class MessengerInMemoryTraySubscriber implements EventSubscriberInterface
{
    /**
     * Ahead of every listener that could report through the messenger.
     */
    protected const int PRIORITY = 512;

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', static::PRIORITY],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        MessengerConfig::setMessageTray(SharedMessengerConfig::IN_MEMORY_TRAY);
    }
}
