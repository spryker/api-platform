<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\EventSubscriber\JsonApiRequestValidatorSubscriber;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\ApiPlatform\Request\RequestAttribute;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group EventSubscriber
 * @group JsonApiRequestValidatorSubscriberTest
 * Add your own group annotations below this line
 */
class JsonApiRequestValidatorSubscriberTest extends Unit
{
    protected const string RESOURCE_SHORT_NAME = 'test-resources';

    protected const string RESOURCE_CLASS = 'Generated\Api\Storefront\TestResource';

    protected const string PATH_COLLECTION = '/test-resources';

    protected const string PATH_NESTED_COLLECTION = '/test-resources/0aa11f2c-9c45-4e21-8a4b-2e2f6f3a9d10/test-items';

    protected const string PATH_NESTED_ITEM_PLACEHOLDER = '/test-resources/0aa11f2c-9c45-4e21-8a4b-2e2f6f3a9d10/test-items/__empty__';

    protected const string ERROR_DETAIL_RESOURCE_ID_NOT_SPECIFIED = 'Resource id is not specified.';

    protected const string BODY_WITH_STRING_ATTRIBUTES = '{"data":{"type":"test-resources","attributes":"x"}}';

    protected const string BODY_WITH_OBJECT_ATTRIBUTES = '{"data":{"type":"test-resources","attributes":{"name":"x"}}}';

    protected ApiUnitTester $tester;

    public function testGivenBodyWhoseAttributesAreNotAnObjectWhenValidatingTheTypeThenTheDocumentIsRejected(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber($this->createMock(RouterInterface::class));
        $event = $this->createWriteRequestEvent(static::BODY_WITH_STRING_ATTRIBUTES);

        // Expect
        $this->expectException(GlueApiException::class);
        $this->expectExceptionMessage(JsonApiRequestValidatorSubscriber::ERROR_DETAIL_POST_DATA_INVALID);

        // Act
        $subscriber->onKernelRequestTypeValidation($event);
    }

    public function testGivenBodyWhoseAttributesAreAnObjectWhenValidatingTheTypeThenItPasses(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber($this->createMock(RouterInterface::class));
        $event = $this->createWriteRequestEvent(static::BODY_WITH_OBJECT_ATTRIBUTES);

        // Act
        $subscriber->onKernelRequestTypeValidation($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenResourceIdPathWhenOnKernelRequestThenMergedSlashRouterProbeIsSkipped(): void
    {
        // Arrange: {resource}/{uuid} — no adjacent resource-name pair, the merged-slash recovery cannot fire
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('match');

        $subscriber = $this->createSubscriber($router);
        $event = $this->createRequestEvent('/test-resources/0aa11f2c-9c45-4e21-8a4b-2e2f6f3a9d10');

        // Act
        $subscriber->onKernelRequestTrailingSlash($event);
    }

    public function testGivenAdjacentResourceNameSegmentsWhenOnKernelRequestThenRouterProbeRuns(): void
    {
        // Arrange: two adjacent resource-name segments are the fingerprint of a collapsed // (nginx merge_slashes)
        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());
        $router->expects($this->atLeastOnce())->method('match')->willThrowException(new ResourceNotFoundException());

        $subscriber = $this->createSubscriber($router);
        $event = $this->createRequestEvent('/test-resources/test-items');

        // Act
        $subscriber->onKernelRequestTrailingSlash($event);
    }

    public function testGivenPatchOnACollectionPathThatHasAPatchOperationWhenOnKernelRequestThenItIsNotRejected(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber($this->createRouterMatching(static::PATH_NESTED_COLLECTION, static::PATH_NESTED_ITEM_PLACEHOLDER));
        $event = $this->createRequestEvent(static::PATH_NESTED_COLLECTION, Request::METHOD_PATCH);

        // Act
        $subscriber->onKernelRequestTrailingSlash($event);

        // Assert
        $this->assertNull($event->getResponse());
    }

    public function testGivenPatchOnACollectionPathWithoutAPatchOperationWhenOnKernelRequestThenTheMissingIdIsRejected(): void
    {
        // Arrange
        $subscriber = $this->createSubscriber($this->createRouterMatching(static::PATH_NESTED_ITEM_PLACEHOLDER));
        $event = $this->createRequestEvent(static::PATH_NESTED_COLLECTION, Request::METHOD_PATCH);

        // Expect
        $this->expectException(GlueApiException::class);
        $this->expectExceptionMessage(static::ERROR_DETAIL_RESOURCE_ID_NOT_SPECIFIED);

        // Act
        $subscriber->onKernelRequestTrailingSlash($event);
    }

    protected function createRouterMatching(string ...$routedPaths): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());
        $router->method('match')->willReturnCallback(
            static function (string $pathInfo) use ($routedPaths): array {
                if (!in_array($pathInfo, $routedPaths, true)) {
                    throw new ResourceNotFoundException();
                }

                return [RequestAttribute::API_RESOURCE_CLASS => static::RESOURCE_CLASS];
            },
        );

        return $router;
    }

    protected function createSubscriber(RouterInterface $router): JsonApiRequestValidatorSubscriber
    {
        return new JsonApiRequestValidatorSubscriber(
            $router,
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
            new IdentityTranslator(),
        );
    }

    protected function createRequestEvent(string $path, string $method = Request::METHOD_GET): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($path, $method),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    protected function createWriteRequestEvent(string $body): RequestEvent
    {
        $request = Request::create(static::PATH_COLLECTION, Request::METHOD_POST, content: $body);
        $request->attributes->set(RequestAttribute::API_RESOURCE_CLASS, static::RESOURCE_CLASS);
        $request->attributes->set(RequestAttribute::API_OPERATION, new Post(shortName: static::RESOURCE_SHORT_NAME));

        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
