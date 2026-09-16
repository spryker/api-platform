<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Codeception\Test\Unit;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use RuntimeException;
use Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber;
use Spryker\ApiPlatform\Validation\NestedObjectValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\IdentityTranslator;
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

    public function testGivenUncaughtThrowableOnApiPlatformRequestInProductionWhenOnKernelExceptionLastResortThenThrowableIsLogged(): void
    {
        // Arrange — the exception that API Platform would otherwise swallow into a generic
        // 500 (SUPESC-1116) must reach the logger so operators are not blind to the cause.
        $logger = $this->createRecordingLogger();
        $subscriber = $this->createSubscriber(debug: false, logger: $logger);
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'Pyz\Glue\CatalogSearchRestApi\Resource\CatalogSearchResource');
        $throwable = new RuntimeException(static::SECRET_MESSAGE);
        $event = $this->createExceptionEvent($request, $throwable);

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $this->assertCount(1, $logger->records);
        $record = $logger->records[0];
        $this->assertSame(LogLevel::ERROR, $record['level']);
        $this->assertStringContainsString(static::SECRET_MESSAGE, $record['message']);
        $this->assertSame($throwable, $record['context']['exception'] ?? null);
    }

    public function testGivenResponseAlreadySetWhenOnKernelExceptionLastResortThenThrowableIsNotLogged(): void
    {
        // Arrange — an earlier subscriber already handled the exception; nothing to log here.
        $logger = $this->createRecordingLogger();
        $subscriber = $this->createSubscriber(debug: false, logger: $logger);
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'Pyz\Glue\CatalogSearchRestApi\Resource\CatalogSearchResource');
        $event = $this->createExceptionEvent($request, new RuntimeException(static::SECRET_MESSAGE));
        $event->setResponse(new Response('handled', Response::HTTP_NOT_FOUND));

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $this->assertCount(0, $logger->records);
    }

    public function testGivenUncaughtThrowableOnFallbackRequestWithoutResolvedOperationWhenOnKernelExceptionLastResortThenResponseIsSanitisedInternalServerError(): void
    {
        // Arrange — thrown before the router resolved an operation: only the proxy marker is present.
        $logger = $this->createRecordingLogger();
        $subscriber = $this->createSubscriber(debug: false, logger: $logger);
        $request = new Request();
        $request->attributes->set('api-platform-request', true);
        $event = $this->createExceptionEvent($request, new RuntimeException(static::SECRET_MESSAGE));

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringNotContainsString(static::SECRET_MESSAGE, (string)$response->getContent());
        $this->assertCount(1, $logger->records);
    }

    public function testGivenUncaughtThrowableOnFallbackRequestWithoutResolvedOperationWhenDebugEnabledThenResponseCarriesExceptionDetails(): void
    {
        // Arrange — API Platform's debug renderer never sees this request, so the guard renders the details itself.
        $logger = $this->createRecordingLogger();
        $subscriber = $this->createSubscriber(debug: true, logger: $logger);
        $request = new Request();
        $request->attributes->set('api-platform-request', true);
        $throwable = new RuntimeException(static::SECRET_MESSAGE);
        $event = $this->createExceptionEvent($request, $throwable);

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
        $error = json_decode((string)$response->getContent(), true)['errors'][0];
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $error['status']);
        $this->assertSame(RuntimeException::class, $error['title']);
        $this->assertSame(static::SECRET_MESSAGE, $error['detail']);
        $this->assertSame($throwable->getFile(), $error['meta']['file']);
        $this->assertSame($throwable->getLine(), $error['meta']['line']);
        $this->assertNotEmpty($error['meta']['trace']);
        $this->assertCount(1, $logger->records);
    }

    public function testGivenHttpExceptionOnFallbackRequestWithoutResolvedOperationWhenOnKernelExceptionLastResortThenResponseIsNotSet(): void
    {
        // Arrange — an unmatched route must leave the kernel so the proxy keeps the original Glue 404.
        $subscriber = $this->createSubscriber(debug: false);
        $request = new Request();
        $request->attributes->set('api-platform-request', true);
        $event = $this->createExceptionEvent($request, new NotFoundHttpException());

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenUncaughtThrowableOnApiPlatformRequestWhenDebugEnabledThenThrowableIsLoggedAndLeftToTheRenderer(): void
    {
        // Arrange — a resolved operation is rendered by API Platform in debug, but the log entry must not depend on it.
        $logger = $this->createRecordingLogger();
        $subscriber = $this->createSubscriber(debug: true, logger: $logger);
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'Pyz\Glue\CatalogSearchRestApi\Resource\CatalogSearchResource');
        $throwable = new RuntimeException(static::SECRET_MESSAGE);
        $event = $this->createExceptionEvent($request, $throwable);

        // Act
        $subscriber->onKernelExceptionLastResort($event);

        // Assert
        $this->assertNull($event->getResponse());
        $this->assertCount(1, $logger->records);
        $this->assertSame($throwable, $logger->records[0]['context']['exception'] ?? null);
        $this->assertStringContainsString(RuntimeException::class, $logger->records[0]['message']);
    }

    protected function createSubscriber(bool $debug = false, ?LoggerInterface $logger = null): GlueApiExceptionSubscriber
    {
        $constraintReader = new ValidationConstraintReader();

        return new GlueApiExceptionSubscriber(
            new IdentityTranslator(),
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
            $constraintReader,
            new NestedObjectValidationErrorAugmenter($constraintReader, new IdentityTranslator()),
            $debug,
            $logger ?? new NullLogger(),
        );
    }

    /**
     * @return \Psr\Log\AbstractLogger&object{records: array<int, array{level: string, message: string, context: array<string, mixed>}>}
     */
    protected function createRecordingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /**
             * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
             */
            public array $records = [];

            /**
             * @param mixed $level
             * @param \Stringable|string $message
             * @param array<string, mixed> $context
             */
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string)$level,
                    'message' => (string)$message,
                    'context' => $context,
                ];
            }
        };
    }

    protected function createExceptionEvent(Request $request, Throwable $throwable): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
