<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\EventSubscriber\AcceptHeaderFallbackSubscriber;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group AcceptHeaderFallbackSubscriberTest
 * Add your own group annotations below this line
 */
class AcceptHeaderFallbackSubscriberTest extends Unit
{
    protected const string JSON_API_MIME_TYPE = 'application/vnd.api+json';

    protected ApiUnitTester $tester;

    public function testGivenMissingAcceptHeaderWhenOnKernelRequestThenAcceptIsSetToJsonApi(): void
    {
        // Arrange
        $subscriber = new AcceptHeaderFallbackSubscriber();
        $request = new Request();
        $event = $this->createRequestEvent($request);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertSame(static::JSON_API_MIME_TYPE, $request->headers->get('Accept'));
    }

    public function testGivenExplicitUnsupportedAcceptHeaderWhenOnKernelRequestThenAcceptIsLeftUnchanged(): void
    {
        // Arrange — preserves vendor's 406 path: validation stays for concrete mismatches.
        $subscriber = new AcceptHeaderFallbackSubscriber();
        $request = new Request();
        $request->headers->set('Accept', 'application/xml');
        $event = $this->createRequestEvent($request);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertSame('application/xml', $request->headers->get('Accept'));
    }

    public function testGivenExplicitJsonApiAcceptHeaderWhenOnKernelRequestThenAcceptIsLeftUnchanged(): void
    {
        // Arrange
        $subscriber = new AcceptHeaderFallbackSubscriber();
        $request = new Request();
        $request->headers->set('Accept', static::JSON_API_MIME_TYPE);
        $event = $this->createRequestEvent($request);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertSame(static::JSON_API_MIME_TYPE, $request->headers->get('Accept'));
    }

    public function testGivenBrowserStyleWildcardAcceptHeaderWhenOnKernelRequestThenOnlyWildcardTokenIsReplacedWithJsonApi(): void
    {
        // Arrange — browser default sends concrete html/xml types plus `*/*;q=0.8`.
        // Only the literal `*/*` token is swapped for vnd.api+json; the concrete types
        // are preserved so their existing negotiation (incl. the 406 path) is untouched.
        $subscriber = new AcceptHeaderFallbackSubscriber();
        $request = new Request();
        $request->headers->set('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
        $event = $this->createRequestEvent($request);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertSame(
            'text/html,application/xhtml+xml,application/xml;q=0.9,application/vnd.api+json;q=0.8',
            $request->headers->get('Accept'),
        );
    }

    public function testGivenAcceptHeaderWithBothJsonApiAndWildcardWhenOnKernelRequestThenJsonApiIsNotAddedTwice(): void
    {
        // Arrange — header already advertises vnd.api+json alongside a wildcard.
        // The subscriber must leave it untouched and never duplicate the mime type.
        $accept = 'application/vnd.api+json,*/*;q=0.8';
        $subscriber = new AcceptHeaderFallbackSubscriber();
        $request = new Request();
        $request->headers->set('Accept', $accept);
        $event = $this->createRequestEvent($request);

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertSame($accept, $request->headers->get('Accept'));
        $this->assertSame(1, substr_count((string)$request->headers->get('Accept'), static::JSON_API_MIME_TYPE));
    }

    public function testGivenSubRequestWhenOnKernelRequestThenAcceptIsLeftUnchanged(): void
    {
        // Arrange — sub-requests must not be rewritten.
        $subscriber = new AcceptHeaderFallbackSubscriber();
        $request = new Request();
        $request->headers->set('Accept', 'application/xml');
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        // Act
        $subscriber->onKernelRequest($event);

        // Assert
        $this->assertSame('application/xml', $request->headers->get('Accept'));
    }

    protected function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
