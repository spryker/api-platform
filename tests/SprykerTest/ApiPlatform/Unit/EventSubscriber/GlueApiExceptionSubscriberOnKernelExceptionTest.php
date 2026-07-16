<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Codeception\Test\Unit;
use InvalidArgumentException;
use Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\ApiPlatform\Validation\NestedObjectValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group GlueApiExceptionSubscriberOnKernelExceptionTest
 * Add your own group annotations below this line
 */
class GlueApiExceptionSubscriberOnKernelExceptionTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenGlueApiExceptionWhenOnKernelExceptionThenReturnsJsonApiErrorWithStatusAndCode(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $exception = new GlueApiException(404, '1503', 'Shopping list not found.');
        $event = $this->createExceptionEvent($exception, new Request());

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());

        $data = $this->decodeResponse($response);
        $this->assertSame('1503', $data['errors'][0]['code']);
        $this->assertSame('Shopping list not found.', $data['errors'][0]['detail']);
    }

    public function testGivenGlueApiExceptionWithPreBuiltErrorsArrayWhenOnKernelExceptionThenReturnsThoseErrorsVerbatim(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $exception = new GlueApiException(422, '1504', '');
        $exception->setErrors([
            [
                'code' => '1504',
                'status' => 422,
                'detail' => 'Shopping list item not found.',
            ],
        ]);
        $event = $this->createExceptionEvent($exception, new Request());

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(422, $response->getStatusCode());

        $data = $this->decodeResponse($response);
        $this->assertSame('1504', $data['errors'][0]['code']);
        $this->assertSame('Shopping list item not found.', $data['errors'][0]['detail']);
    }

    public function testGivenBadRequestHttpExceptionWithApiResourceClassWhenOnKernelExceptionThenReturns422WithCode901(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $exception = new BadRequestHttpException('Validation failed.');
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'SomeResourceClass');
        $event = $this->createExceptionEvent($exception, $request);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = $this->decodeResponse($response);
        $this->assertSame('901', $data['errors'][0]['code']);
    }

    public function testGivenPropertyAccessorInvalidArgumentExceptionWithApiResourceClassWhenOnKernelExceptionThenReturns422WithPropertyDetail(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $exception = new InvalidArgumentException('Expected argument of type "?int", "string" given at property path "quantity".');
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'SomeResourceClass');
        $event = $this->createExceptionEvent($exception, $request);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = $this->decodeResponse($response);
        $this->assertSame('quantity => This value should be of type numeric.', $data['errors'][0]['detail']);
    }

    public function testGivenMethodNotAllowedHttpExceptionWithoutApiResourceClassWhenOnKernelExceptionThenReturns404(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $exception = new MethodNotAllowedHttpException(['GET']);
        $request = new Request();
        // No _api_resource_class attribute set
        $event = $this->createExceptionEvent($exception, $request);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGivenAccessDeniedHttpExceptionWithApiResourceClassWhenOnKernelExceptionThenReturns403JsonApiResponse(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $exception = new AccessDeniedHttpException();
        $request = new Request();
        $request->attributes->set('_api_resource_class', 'SomeResourceClass');
        $event = $this->createExceptionEvent($exception, $request);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/vnd.api+json', (string)$contentType);
    }

    public function testGivenAccessDeniedExceptionWithoutBearerTokenWhenOnKernelExceptionThenReturns403WithMissingTokenCode(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber();
        $exception = new AccessDeniedException();
        // No Authorization header, no _api_resource_class set (non-backend resource)
        $request = new Request();
        $event = $this->createExceptionEvent($exception, $request);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $data = $this->decodeResponse($response);
        $this->assertSame('002', $data['errors'][0]['code']);
        $this->assertSame('Missing access token.', $data['errors'][0]['detail']);
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

    protected function createExceptionEvent(mixed $throwable, Request $request): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(Response $response): array
    {
        $content = $response->getContent();
        $this->assertNotFalse($content, 'Response content must not be empty.');

        $data = json_decode($content, true);
        $this->assertIsArray($data, 'Response must be valid JSON.');

        return $data;
    }
}
