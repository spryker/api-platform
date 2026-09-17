<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use Codeception\Test\Unit;
use ReflectionProperty;
use Spryker\ApiPlatform\EventSubscriber\MessengerInMemoryTraySubscriber;
use Spryker\Shared\Messenger\MessengerConfig as SharedMessengerConfig;
use Spryker\Zed\Messenger\MessengerConfig;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group MessengerInMemoryTraySubscriberTest
 * Add your own group annotations below this line
 */
class MessengerInMemoryTraySubscriberTest extends Unit
{
    /**
     * @uses \Spryker\Zed\Messenger\MessengerConfig::$messageTray
     */
    protected const string MESSENGER_CONFIG_PROPERTY_MESSAGE_TRAY = 'messageTray';

    protected ApiUnitTester $tester;

    protected function _before(): void
    {
        $this->setConfiguredTray(SharedMessengerConfig::SESSION_TRAY);
    }

    protected function _after(): void
    {
        $this->setConfiguredTray(SharedMessengerConfig::SESSION_TRAY);
    }

    public function testGivenApiRequestWhenOnKernelRequestThenMessengerUsesTheInMemoryTray(): void
    {
        // Arrange
        $subscriber = new MessengerInMemoryTraySubscriber();

        // Act
        $subscriber->onKernelRequest($this->createRequestEvent());

        // Assert
        $this->assertSame(
            SharedMessengerConfig::IN_MEMORY_TRAY,
            $this->getConfiguredTray(),
            'An API request has no session, so the messenger must not be pointed at the session tray.',
        );
    }

    public function testGivenSubRequestWhenOnKernelRequestThenTheConfiguredTrayIsLeftUnchanged(): void
    {
        // Arrange
        $subscriber = new MessengerInMemoryTraySubscriber();

        // Act
        $subscriber->onKernelRequest($this->createRequestEvent(HttpKernelInterface::SUB_REQUEST));

        // Assert
        $this->assertSame(SharedMessengerConfig::SESSION_TRAY, $this->getConfiguredTray());
    }

    public function testSubscribesToKernelRequest(): void
    {
        // Act
        $subscribedEvents = MessengerInMemoryTraySubscriber::getSubscribedEvents();

        // Assert
        $this->assertArrayHasKey(KernelEvents::REQUEST, $subscribedEvents);
    }

    public function testRunsBeforeTheControllerSoAProcessorCanRaiseAMessage(): void
    {
        // Act
        $priority = MessengerInMemoryTraySubscriber::getSubscribedEvents()[KernelEvents::REQUEST][1];

        // Assert
        $this->assertGreaterThan(0, $priority);
    }

    protected function createRequestEvent(int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), $requestType);
    }

    protected function getConfiguredTray(): string
    {
        return (string)(new ReflectionProperty(
            MessengerConfig::class,
            static::MESSENGER_CONFIG_PROPERTY_MESSAGE_TRAY,
        ))->getValue();
    }

    protected function setConfiguredTray(string $tray): void
    {
        MessengerConfig::setMessageTray($tray);
    }
}
