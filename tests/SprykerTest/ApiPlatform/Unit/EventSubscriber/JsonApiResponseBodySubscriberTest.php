<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\EventSubscriber\JsonApiResponseBodySubscriber;
use Spryker\ApiPlatform\ResponseTransform\JsonApiRelationshipNormalizerTransform;
use Spryker\ApiPlatform\ResponseTransform\JsonApiResolvedRelationshipTransform;
use Spryker\ApiPlatform\ResponseTransform\PaginationLinksTransform;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group JsonApiResponseBodySubscriberTest
 * Add your own group annotations below this line
 */
class JsonApiResponseBodySubscriberTest extends Unit
{
    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    protected ApiUnitTester $tester;

    public function testGivenNonJsonApiContentTypeWhenOnKernelResponseThenTransformsAreNotInvoked(): void
    {
        // Arrange
        $subscriber = $this->createSubscriberWithNeverInvokedTransforms();
        $event = $this->createResponseEvent('{"data":{"relationships":{}}}', 'application/json');

        // Act
        $subscriber->onKernelResponse($event);
    }

    public function testGivenBodyWithoutMarkersWhenOnKernelResponseThenBodyIsUntouchedAndDecodeIsSkipped(): void
    {
        // Arrange
        $content = '{"data":{"id":"1","type":"test-resources","attributes":{"name":"Imprint","pagination":{}}}}';
        $subscriber = $this->createSubscriberWithNeverInvokedTransforms();
        $event = $this->createResponseEvent($content);

        // Act
        $subscriber->onKernelResponse($event);

        // Assert
        $this->assertSame($content, $event->getResponse()->getContent());
    }

    public function testGivenRelationshipsMarkerWhenOnKernelResponseThenTransformsRunInOriginalPriorityOrder(): void
    {
        // Arrange
        $invocations = [];

        $relationshipNormalizer = $this->createMock(JsonApiRelationshipNormalizerTransform::class);
        $relationshipNormalizer->expects($this->once())->method('applyTo')->willReturnCallback(
            static function () use (&$invocations): bool {
                $invocations[] = 'relationshipNormalizer';

                return false;
            },
        );
        $resolvedRelationship = $this->createMock(JsonApiResolvedRelationshipTransform::class);
        $resolvedRelationship->expects($this->once())->method('applyTo')->willReturnCallback(
            static function () use (&$invocations): bool {
                $invocations[] = 'resolvedRelationship';

                return false;
            },
        );
        $paginationLinks = $this->createMock(PaginationLinksTransform::class);
        $paginationLinks->expects($this->once())->method('applyTo')->willReturnCallback(
            static function () use (&$invocations): bool {
                $invocations[] = 'paginationLinks';

                return false;
            },
        );

        $subscriber = new JsonApiResponseBodySubscriber($relationshipNormalizer, $resolvedRelationship, $paginationLinks);
        $event = $this->createResponseEvent('{"data":{"id":"1","type":"test-resources","relationships":{}}}');

        // Act
        $subscriber->onKernelResponse($event);

        // Assert: same order as the former subscriber priorities -257 -> -258 -> -259
        $this->assertSame(['relationshipNormalizer', 'resolvedRelationship', 'paginationLinks'], $invocations);
    }

    public function testGivenNullPaginationAttributeWhenOnKernelResponseThenAttributeIsStripped(): void
    {
        // Arrange: single resource, no relationships/included/currentPage markers — only "pagination":null
        $content = '{"data":{"id":"1","type":"test-resources","attributes":{"email":"a@b.c","pagination":null}}}';
        $subscriber = $this->createSubscriberWithRealTransforms();
        $event = $this->createResponseEvent($content);

        // Act
        $subscriber->onKernelResponse($event);

        // Assert
        $data = json_decode((string)$event->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('pagination', $data['data']['attributes']);
        $this->assertSame('a@b.c', $data['data']['attributes']['email']);
    }

    public function testGivenPaginationWithCurrentPageWhenOnKernelResponseThenPaginationLinksAreAdded(): void
    {
        // Arrange
        $content = '{"data":[{"id":"1","type":"test-resources","attributes":{"pagination":{"currentPage":1,"maxPage":2,"itemsPerPage":10}}}]}';
        $subscriber = $this->createSubscriberWithRealTransforms();
        $event = $this->createResponseEvent($content);

        // Act
        $subscriber->onKernelResponse($event);

        // Assert
        $data = json_decode((string)$event->getResponse()->getContent(), true);
        $this->assertArrayHasKey('first', $data['links'] ?? []);
        $this->assertArrayHasKey('last', $data['links']);
        $this->assertArrayHasKey('next', $data['links']);
        $this->assertStringNotContainsString('\/', (string)$event->getResponse()->getContent());
    }

    protected function createSubscriberWithNeverInvokedTransforms(): JsonApiResponseBodySubscriber
    {
        $relationshipNormalizer = $this->createMock(JsonApiRelationshipNormalizerTransform::class);
        $relationshipNormalizer->expects($this->never())->method('applyTo');
        $resolvedRelationship = $this->createMock(JsonApiResolvedRelationshipTransform::class);
        $resolvedRelationship->expects($this->never())->method('applyTo');
        $paginationLinks = $this->createMock(PaginationLinksTransform::class);
        $paginationLinks->expects($this->never())->method('applyTo');

        return new JsonApiResponseBodySubscriber($relationshipNormalizer, $resolvedRelationship, $paginationLinks);
    }

    protected function createSubscriberWithRealTransforms(): JsonApiResponseBodySubscriber
    {
        return new JsonApiResponseBodySubscriber(
            new JsonApiRelationshipNormalizerTransform(),
            new JsonApiResolvedRelationshipTransform($this->createMock(NormalizerInterface::class)),
            new PaginationLinksTransform(),
        );
    }

    protected function createResponseEvent(string $content, string $contentType = self::CONTENT_TYPE_JSON_API): ResponseEvent
    {
        $request = Request::create('/test-resources');
        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => $contentType]);

        return new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
