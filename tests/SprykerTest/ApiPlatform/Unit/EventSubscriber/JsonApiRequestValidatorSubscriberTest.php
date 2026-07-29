<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\EventSubscriber;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\EventSubscriber\JsonApiRequestValidatorSubscriber;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

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
    protected ApiUnitTester $tester;

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

    protected function createSubscriber(RouterInterface $router): JsonApiRequestValidatorSubscriber
    {
        return new JsonApiRequestValidatorSubscriber(
            $router,
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
        );
    }

    protected function createRequestEvent(string $path): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
