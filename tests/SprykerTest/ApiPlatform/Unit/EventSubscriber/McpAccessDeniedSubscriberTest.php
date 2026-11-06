<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\Exception\AccessDeniedException;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\EventSubscriber\McpAccessDeniedSubscriber;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group McpAccessDeniedSubscriberTest
 * Add your own group annotations below this line
 */
class McpAccessDeniedSubscriberTest extends Unit
{
    protected const string SECURITY_MESSAGE = 'Customer only.';

    public function testGivenRecordedMcpAccessDenialWhenRespondingThenSecurityAccessDeniedIsThrownForTheFirewall(): void
    {
        // Arrange
        $event = $this->createResponseEvent();
        $event->getRequest()->attributes->set(RequestAttribute::MCP_ACCESS_DENIED, new AccessDeniedException(static::SECURITY_MESSAGE));

        // Act
        $thrown = null;

        try {
            (new McpAccessDeniedSubscriber())->onKernelResponse($event);
        } catch (SecurityAccessDeniedException $exception) {
            $thrown = $exception;
        }

        // Assert
        $this->assertInstanceOf(SecurityAccessDeniedException::class, $thrown);
        $this->assertSame(static::SECURITY_MESSAGE, $thrown->getMessage());
        $this->assertFalse($event->getRequest()->attributes->has(RequestAttribute::MCP_ACCESS_DENIED));
    }

    public function testGivenNoRecordedMcpAccessDenialWhenRespondingThenTheResponseIsKept(): void
    {
        // Arrange
        $event = $this->createResponseEvent();
        $response = $event->getResponse();

        // Act
        (new McpAccessDeniedSubscriber())->onKernelResponse($event);

        // Assert
        $this->assertSame($response, $event->getResponse());
        $this->assertSame(Response::HTTP_OK, $event->getResponse()->getStatusCode());
    }

    protected function createResponseEvent(): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/mcp', Request::METHOD_POST),
            HttpKernelInterface::MAIN_REQUEST,
            new Response('{"jsonrpc":"2.0"}'),
        );
    }
}
