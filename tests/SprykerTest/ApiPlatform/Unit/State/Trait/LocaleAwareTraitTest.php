<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\State\Trait;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\LocaleTransfer;
use Spryker\ApiPlatform\Exception\ApiPlatformContextException;
use Spryker\ApiPlatform\State\Trait\LocaleAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group State
 * @group Trait
 * @group LocaleAwareTraitTest
 * Add your own group annotations below this line
 */
class LocaleAwareTraitTest extends Unit
{
    protected const string LOCALE_NAME = 'de_DE';

    public function testGivenRequestWithLocaleAttributeWhenHasLocaleThenReturnsTrue(): void
    {
        // Arrange
        $fixture = $this->createFixture($this->createRequestWithLocale(static::LOCALE_NAME));

        // Act
        $result = $fixture->callHasLocale();

        // Assert
        $this->assertTrue($result);
    }

    public function testGivenRequestWithoutLocaleAttributeWhenHasLocaleThenReturnsFalse(): void
    {
        // Arrange
        $fixture = $this->createFixture(new Request());

        // Act
        $result = $fixture->callHasLocale();

        // Assert
        $this->assertFalse($result);
    }

    public function testGivenNoRequestWhenHasLocaleThenReturnsFalse(): void
    {
        // Arrange
        $fixture = $this->createFixture(null);

        // Act
        $result = $fixture->callHasLocale();

        // Assert
        $this->assertFalse($result);
    }

    public function testGivenRequestWithLocaleAttributeWhenGetLocaleThenReturnsLocaleTransfer(): void
    {
        // Arrange
        $fixture = $this->createFixture($this->createRequestWithLocale(static::LOCALE_NAME));

        // Act
        $result = $fixture->callGetLocale();

        // Assert
        $this->assertInstanceOf(LocaleTransfer::class, $result);
        $this->assertSame(static::LOCALE_NAME, $result->getLocaleName());
    }

    public function testGivenNoLocaleWhenGetLocaleThenThrowsApiPlatformContextException(): void
    {
        // Arrange
        $fixture = $this->createFixture(new Request());

        // Assert
        $this->expectException(ApiPlatformContextException::class);

        // Act
        $fixture->callGetLocale();
    }

    public function testGivenRequestWithLocaleAttributeWhenFindLocaleThenReturnsLocaleTransfer(): void
    {
        // Arrange
        $fixture = $this->createFixture($this->createRequestWithLocale(static::LOCALE_NAME));

        // Act
        $result = $fixture->callFindLocale();

        // Assert
        $this->assertInstanceOf(LocaleTransfer::class, $result);
    }

    public function testGivenNoLocaleWhenFindLocaleThenReturnsNull(): void
    {
        // Arrange
        $fixture = $this->createFixture(new Request());

        // Act
        $result = $fixture->callFindLocale();

        // Assert
        $this->assertNull($result);
    }

    public function testGivenNoRequestWhenFindLocaleThenReturnsNull(): void
    {
        // Arrange
        $fixture = $this->createFixture(null);

        // Act
        $result = $fixture->callFindLocale();

        // Assert
        $this->assertNull($result);
    }

    public function testGivenRequestWithLocaleAttributeWhenFindLocaleNameThenReturnsLocaleName(): void
    {
        // Arrange
        $fixture = $this->createFixture($this->createRequestWithLocale(static::LOCALE_NAME));

        // Act
        $result = $fixture->callFindLocaleName();

        // Assert
        $this->assertSame(static::LOCALE_NAME, $result);
    }

    public function testGivenNoLocaleWhenFindLocaleNameThenReturnsNull(): void
    {
        // Arrange
        $fixture = $this->createFixture(new Request());

        // Act
        $result = $fixture->callFindLocaleName();

        // Assert
        $this->assertNull($result);
    }

    protected function createRequestWithLocale(string $localeName): Request
    {
        $request = new Request();
        $request->attributes->set('LocaleTransfer', (new LocaleTransfer())->setLocaleName($localeName));

        return $request;
    }

    protected function createFixture(?Request $request): object
    {
        return new class ($request) {
            use LocaleAwareTrait;

            public function __construct(protected ?Request $request)
            {
            }

            public function callHasLocale(): bool
            {
                return $this->hasLocale();
            }

            public function callGetLocale(): LocaleTransfer
            {
                return $this->getLocale();
            }

            public function callFindLocale(): ?LocaleTransfer
            {
                return $this->findLocale();
            }

            public function callFindLocaleName(): ?string
            {
                return $this->findLocaleName();
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
