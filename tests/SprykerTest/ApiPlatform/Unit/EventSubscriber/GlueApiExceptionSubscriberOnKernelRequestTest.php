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
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\ApiPlatform\Validation\NestedObjectValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\IdentityTranslator;

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
    protected const string LOCALE_NAME_DE_DE = 'de_DE';

    protected const string LOCALE_NAME_FR_FR = 'fr_FR';

    protected const string LANGUAGE_CODE_EN = 'en';

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

    /**
     * A constraint violation is interpolated into its final text the moment the validator creates
     * it, from the translator's current locale — so this listener is the last point at which the
     * language of a validation message can be chosen.
     */
    public function testOnKernelRequestSetValidationLocaleAdoptsTheLocaleResolvedForTheRequest(): void
    {
        // Arrange
        $translator = new IdentityTranslator();
        $subscriber = $this->createSubscriber($translator);
        $event = $this->createRequestEvent('POST', '{}', true);
        $event->getRequest()->attributes->set(RequestAttribute::LOCALE, static::LOCALE_NAME_DE_DE);

        // Act
        $subscriber->onKernelRequestSetValidationLocale($event);

        // Assert
        $this->assertSame(static::LOCALE_NAME_DE_DE, $translator->getLocale());
    }

    /**
     * What a caller sending no `Accept-Language` has always received.
     */
    public function testOnKernelRequestSetValidationLocaleFallsBackToEnglishWhenNoLocaleWasResolved(): void
    {
        // Arrange
        $translator = new IdentityTranslator();
        $translator->setLocale(static::LOCALE_NAME_DE_DE);
        $subscriber = $this->createSubscriber($translator);
        $event = $this->createRequestEvent('POST', '{}', true);

        // Act
        $subscriber->onKernelRequestSetValidationLocale($event);

        // Assert
        $this->assertSame(static::LANGUAGE_CODE_EN, $translator->getLocale());
    }

    /**
     * Legacy Glue endpoints resolve their own locale after validation runs, and must keep doing so.
     */
    public function testOnKernelRequestSetValidationLocaleLeavesNonApiPlatformRoutesAlone(): void
    {
        // Arrange
        $translator = new IdentityTranslator();
        $translator->setLocale(static::LOCALE_NAME_FR_FR);
        $subscriber = $this->createSubscriber($translator);
        $event = $this->createRequestEvent('POST', '{}', false);
        $event->getRequest()->attributes->set(RequestAttribute::LOCALE, static::LOCALE_NAME_DE_DE);

        // Act
        $subscriber->onKernelRequestSetValidationLocale($event);

        // Assert
        $this->assertSame(static::LOCALE_NAME_FR_FR, $translator->getLocale());
    }

    protected function createSubscriber(?IdentityTranslator $translator = null): GlueApiExceptionSubscriber
    {
        $constraintReader = new ValidationConstraintReader();

        return new GlueApiExceptionSubscriber(
            $translator ?? new IdentityTranslator(),
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
            $constraintReader,
            new NestedObjectValidationErrorAugmenter($constraintReader, new IdentityTranslator()),
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
