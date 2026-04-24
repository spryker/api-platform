<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State\Trait;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StoreTransfer;
use Spryker\ApiPlatform\Exception\ApiPlatformContextException;
use Spryker\ApiPlatform\State\Trait\StoreAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group Trait
 * @group StoreAwareTraitTest
 * Add your own group annotations below this line
 */
class StoreAwareTraitTest extends Unit
{
    protected const string STORE_NAME = 'DE';

    public function testGivenRequestWithStoreAttributeWhenHasStoreThenReturnsTrue(): void
    {
        // Arrange
        $fixture = $this->createFixture($this->createRequestWithStore(static::STORE_NAME));

        // Act
        $result = $fixture->callHasStore();

        // Assert
        $this->assertTrue($result);
    }

    public function testGivenRequestWithoutStoreAttributeWhenHasStoreThenReturnsFalse(): void
    {
        // Arrange
        $fixture = $this->createFixture(new Request());

        // Act
        $result = $fixture->callHasStore();

        // Assert
        $this->assertFalse($result);
    }

    public function testGivenNoRequestWhenHasStoreThenReturnsFalse(): void
    {
        // Arrange
        $fixture = $this->createFixture(null);

        // Act
        $result = $fixture->callHasStore();

        // Assert
        $this->assertFalse($result);
    }

    public function testGivenRequestWithStoreAttributeWhenGetStoreThenReturnsStoreTransfer(): void
    {
        // Arrange
        $fixture = $this->createFixture($this->createRequestWithStore(static::STORE_NAME));

        // Act
        $result = $fixture->callGetStore();

        // Assert
        $this->assertInstanceOf(StoreTransfer::class, $result);
        $this->assertSame(static::STORE_NAME, $result->getName());
    }

    public function testGivenNoStoreWhenGetStoreThenThrowsApiPlatformContextException(): void
    {
        // Arrange
        $fixture = $this->createFixture(new Request());

        // Assert
        $this->expectException(ApiPlatformContextException::class);

        // Act
        $fixture->callGetStore();
    }

    public function testGivenRequestWithStoreAttributeWhenFindStoreThenReturnsStoreTransfer(): void
    {
        // Arrange
        $fixture = $this->createFixture($this->createRequestWithStore(static::STORE_NAME));

        // Act
        $result = $fixture->callFindStore();

        // Assert
        $this->assertInstanceOf(StoreTransfer::class, $result);
    }

    public function testGivenNoStoreWhenFindStoreThenReturnsNull(): void
    {
        // Arrange
        $fixture = $this->createFixture(new Request());

        // Act
        $result = $fixture->callFindStore();

        // Assert
        $this->assertNull($result);
    }

    public function testGivenNoRequestWhenFindStoreThenReturnsNull(): void
    {
        // Arrange
        $fixture = $this->createFixture(null);

        // Act
        $result = $fixture->callFindStore();

        // Assert
        $this->assertNull($result);
    }

    public function testGivenRequestWithStoreAttributeWhenFindStoreNameThenReturnsStoreName(): void
    {
        // Arrange
        $fixture = $this->createFixture($this->createRequestWithStore(static::STORE_NAME));

        // Act
        $result = $fixture->callFindStoreName();

        // Assert
        $this->assertSame(static::STORE_NAME, $result);
    }

    public function testGivenNoStoreWhenFindStoreNameThenReturnsNull(): void
    {
        // Arrange
        $fixture = $this->createFixture(new Request());

        // Act
        $result = $fixture->callFindStoreName();

        // Assert
        $this->assertNull($result);
    }

    protected function createRequestWithStore(string $storeName): Request
    {
        $request = new Request();
        $request->attributes->set('StoreTransfer', (new StoreTransfer())->setName($storeName));

        return $request;
    }

    protected function createFixture(?Request $request): object
    {
        return new class ($request) {
            use StoreAwareTrait;

            public function __construct(protected ?Request $request)
            {
            }

            public function callHasStore(): bool
            {
                return $this->hasStore();
            }

            public function callGetStore(): StoreTransfer
            {
                return $this->getStore();
            }

            public function callFindStore(): ?StoreTransfer
            {
                return $this->findStore();
            }

            public function callFindStoreName(): ?string
            {
                return $this->findStoreName();
            }

            protected function hasRequest(): bool
            {
                return $this->request !== null;
            }

            protected function getRequest(): Request
            {
                return $this->request;
            }
        };
    }
}
