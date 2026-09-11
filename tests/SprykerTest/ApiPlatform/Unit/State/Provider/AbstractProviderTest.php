<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State\Provider;

use ApiPlatform\Metadata\GetCollection;
use Codeception\Test\Unit;
use SprykerTest\ApiPlatform\Fixture\PaginationLimitFixtureProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group Provider
 * @group AbstractProviderTest
 * Add your own group annotations below this line
 */
class AbstractProviderTest extends Unit
{
    public function testGivenRequestedLimitAboveTheDeclaredMaximumWhenResolvingPaginationLimitThenItIsClamped(): void
    {
        // Arrange
        $operation = (new GetCollection())->withPaginationMaximumItemsPerPage(50);
        $context = ['request' => $this->createRequest(200)];
        $provider = new PaginationLimitFixtureProvider();

        // Act
        $limit = $provider->callGetPaginationLimit($operation, $context);

        // Assert
        $this->assertSame(50, $limit);
    }

    public function testGivenNoDeclaredMaximumWhenResolvingPaginationLimitThenTheRequestedLimitIsUnclamped(): void
    {
        // Arrange
        $operation = new GetCollection();
        $context = ['request' => $this->createRequest(200)];
        $provider = new PaginationLimitFixtureProvider();

        // Act
        $limit = $provider->callGetPaginationLimit($operation, $context);

        // Assert
        $this->assertSame(200, $limit);
    }

    public function testGivenRequestedLimitBelowTheDeclaredMaximumWhenResolvingPaginationLimitThenItPassesThroughUnchanged(): void
    {
        // Arrange
        $operation = (new GetCollection())->withPaginationMaximumItemsPerPage(50);
        $context = ['request' => $this->createRequest(20)];
        $provider = new PaginationLimitFixtureProvider();

        // Act
        $limit = $provider->callGetPaginationLimit($operation, $context);

        // Assert
        $this->assertSame(20, $limit);
    }

    public function testGivenExplicitDefaultBelowTheDeclaredMaximumAndNoRequestedLimitWhenResolvingPaginationLimitThenTheDefaultPassesThroughUnchanged(): void
    {
        // Arrange
        $operation = (new GetCollection())->withPaginationMaximumItemsPerPage(50);
        $context = ['request' => $this->createRequest(null)];
        $provider = new PaginationLimitFixtureProvider();

        // Act
        $limit = $provider->callGetPaginationLimit($operation, $context, 15);

        // Assert
        $this->assertSame(15, $limit);
    }

    public function testGivenNonPositiveRequestedLimitWhenResolvingPaginationLimitThenTheDefaultIsUsed(): void
    {
        // Arrange
        $operation = (new GetCollection())->withPaginationItemsPerPage(10);
        $context = ['request' => $this->createRequest(0)];
        $provider = new PaginationLimitFixtureProvider();

        // Act
        $limit = $provider->callGetPaginationLimit($operation, $context);

        // Assert
        $this->assertSame(10, $limit);
    }

    public function testGivenNegativeRequestedOffsetWhenResolvingPaginationOffsetThenItIsClampedToZero(): void
    {
        // Arrange
        $context = ['request' => new Request(['page' => ['offset' => -5]])];
        $provider = new PaginationLimitFixtureProvider();

        // Act
        $offset = $provider->callGetPaginationOffset(new GetCollection(), $context);

        // Assert
        $this->assertSame(0, $offset);
    }

    public function testGivenNoRequestedOffsetWhenResolvingPaginationOffsetThenTheGivenDefaultIsUsed(): void
    {
        // Arrange
        $context = ['request' => new Request()];
        $provider = new PaginationLimitFixtureProvider();

        // Act
        $offset = $provider->callGetPaginationOffset(new GetCollection(), $context, 20);

        // Assert
        $this->assertSame(20, $offset);
    }

    protected function createRequest(?int $requestedLimit): Request
    {
        return new Request($requestedLimit === null ? [] : ['page' => ['limit' => $requestedLimit]]);
    }
}
