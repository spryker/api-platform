<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber;
use Spryker\ApiPlatform\Validation\NestedObjectValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group GlueApiExceptionSubscriberOnKernelRequestTest
 * Add your own group annotations below this line
 */
class GlueApiExceptionSubscriberOnKernelRequestTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenEmptyPostBodyWithApiResourceClassWhenOnKernelRequestThenReturnsBadRequestResponse(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('POST', '', true);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Post data missing or invalid.', (string)$response->getContent());
    }

    public function testGivenEmptyJsonObjectWithApiResourceClassWhenOnKernelRequestThenReturnsBadRequestResponse(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('POST', '{}', true);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Post data missing or invalid.', (string)$response->getContent());
    }

    public function testGivenGetRequestWithApiResourceClassWhenOnKernelRequestThenNoResponseIsSet(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('GET', '', true);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenPostRequestWithoutApiResourceClassWhenOnKernelRequestThenNoResponseIsSet(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('POST', '', false);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenPostWithValidBodyAndApiResourceClassWhenOnKernelRequestThenNoResponseIsSet(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('POST', '{"data":{"attributes":{"name":"test"}}}', true);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenSubRequestWithEmptyBodyAndApiResourceClassWhenOnKernelRequestThenNoResponseIsSet(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('POST', '', true, HttpKernelInterface::SUB_REQUEST);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    protected function createSubscriber(): GlueApiExceptionSubscriber
    {
        $constraintReader = new ValidationConstraintReader();

        return new GlueApiExceptionSubscriber(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
            $constraintReader,
            new NestedObjectValidationErrorAugmenter($constraintReader),
            true,
        );
    }

    protected function createRequestEvent(
        string $method,
        string $content,
        bool $withApiResourceClass,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        $request = Request::create('/test', $method, [], [], [], [], $content);

        if ($withApiResourceClass) {
            $request->attributes->set('_api_resource_class', 'SomeResourceClass');
        }

        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, $requestType);
    }
}
