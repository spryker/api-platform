<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Codeception\Test\Unit;
use RuntimeException;
use Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group GlueApiExceptionSubscriberThrowableTest
 * Add your own group annotations below this line
 */
class GlueApiExceptionSubscriberThrowableTest extends Unit
{
    protected const string SECRET_MESSAGE = 'boom at /var/www/secret/internal.php line 42';

    protected ApiUnitTester $tester;

    public function testGivenUncaughtThrowableOnApiPlatformRequestWhenOnKernelExceptionLastResortThenResponseIsSanitisedInternalServerError(): void
    {
        // Arrange — production (debug off): traces must never reach the client.
        $subscriber = $this->createSubscriber(debug: false);
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'Pyz\Glue\CatalogSearchRestApi\Resource\CatalogSearchResource');
        $event = $this->createExceptionEvent($request, new RuntimeException(static::SECRET_MESSAGE));

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $body = (string)$response->getContent();
        $this->assertStringNotContainsString('internal.php', $body);
        $this->assertStringNotContainsString('/var/www', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('boom', $body);
        $this->assertStringNotContainsString('#0', $body);
    }

    public function testGivenUncaughtThrowableOnApiPlatformRequestWhenDebugEnabledThenResponseIsNotSet(): void
    {
        // Arrange — development (debug on): the last-resort guard steps aside so the
        // throwable propagates to API Platform's debug error renderer and the developer
        // sees the full message, file and stack trace.
        $subscriber = $this->createSubscriber(debug: true);
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'Pyz\Glue\CatalogSearchRestApi\Resource\CatalogSearchResource');
        $event = $this->createExceptionEvent($request, new RuntimeException(static::SECRET_MESSAGE));

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenUncaughtThrowableOnNonApiPlatformRequestWhenOnKernelExceptionLastResortThenResponseIsNotSet(): void
    {
        // Arrange — legacy Glue / Yves / Zed paths must keep their own error handling.
        $subscriber = $this->createSubscriber(debug: false);
        $request = new Request();
        $event = $this->createExceptionEvent($request, new RuntimeException(static::SECRET_MESSAGE));

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenHttpExceptionOnApiPlatformRequestWhenOnKernelExceptionLastResortThenResponseIsNotSet(): void
    {
        // Arrange — HTTP exceptions keep their status: direct ones are handled at priority 256,
        // OAuth-converted ones (401) must keep flowing to API Platform's renderer, not become 500.
        $subscriber = $this->createSubscriber(debug: false);
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'Pyz\Glue\CatalogSearchRestApi\Resource\CatalogSearchResource');
        $event = $this->createExceptionEvent($request, new UnauthorizedHttpException('Bearer'));

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenResponseAlreadySetWhenOnKernelExceptionLastResortThenResponseIsNotOverwritten(): void
    {
        // Arrange — never override a response set by an earlier exception subscriber.
        $subscriber = $this->createSubscriber(debug: false);
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'Pyz\Glue\CatalogSearchRestApi\Resource\CatalogSearchResource');
        $event = $this->createExceptionEvent($request, new RuntimeException(static::SECRET_MESSAGE));
        $existingResponse = new Response('handled', Response::HTTP_NOT_FOUND);
        $event->setResponse($existingResponse);

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $this->assertSame($existingResponse, $event->getResponse());
    }

    protected function createSubscriber(bool $debug = false): GlueApiExceptionSubscriber
    {
        return new GlueApiExceptionSubscriber(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
            $debug,
        );
    }

    protected function createExceptionEvent(Request $request, Throwable $throwable): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
