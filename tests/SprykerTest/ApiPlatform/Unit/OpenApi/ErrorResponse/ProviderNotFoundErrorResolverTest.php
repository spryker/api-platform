<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Codeception\Test\Unit;
use Error;
use Spryker\ApiPlatform\Exception\AmbiguousNotFoundErrorDeclarationException;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ProviderNotFoundErrorResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;
use SprykerTest\ApiPlatform\Fixture\AmbiguousNotFoundErrorFixtureProvider;
use SprykerTest\ApiPlatform\Fixture\ErrorResponseFixtureProvider;
use SprykerTest\ApiPlatform\Fixture\ErrorResponseFixtureResource;
use SprykerTest\ApiPlatform\Fixture\MalformedNotFoundErrorFixtureProvider;
use SprykerTest\ApiPlatform\Fixture\PaginationLimitFixtureProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group ErrorResponse
 * @group ProviderNotFoundErrorResolverTest
 * Add your own group annotations below this line
 */
class ProviderNotFoundErrorResolverTest extends Unit
{
    protected const string UNKNOWN_CLASS = 'SprykerTest\ApiPlatform\Fixture\DoesNotExist';

    protected const string NOT_FOUND_CODE_CUSTOMER = '1201';

    protected const int NOT_FOUND_CODE_NUMERIC = 1201;

    protected const string NOT_FOUND_MESSAGE_CUSTOMER = 'Customer with reference "{customerReference}" was not found.';

    protected ApiUnitTester $tester;

    public function testGivenProviderWithNotFoundConstantsWhenResolvingByProviderThenReturnsTheGlueError(): void
    {
        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveByProviderClass(ErrorResponseFixtureProvider::class);

        // Assert
        $this->assertSame([
            'status' => Response::HTTP_NOT_FOUND,
            'detail' => ErrorResponseFixtureProvider::ERROR_MESSAGE_CATEGORY_NOT_FOUND,
            'message' => ErrorResponseFixtureProvider::ERROR_MESSAGE_CATEGORY_NOT_FOUND,
            'code' => ErrorResponseFixtureProvider::ERROR_CODE_CATEGORY_NOT_FOUND,
        ], $error);
    }

    public function testGivenResourceWithProviderWhenResolvingByResourceThenReadsTheProviderFromTheApiResourceAttribute(): void
    {
        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveByResourceClass(ErrorResponseFixtureResource::class);

        // Assert
        $this->assertSame(ErrorResponseFixtureProvider::ERROR_CODE_CATEGORY_NOT_FOUND, $error['code'] ?? null);
    }

    public function testGivenProviderWithoutNotFoundConstantsWhenResolvingThenReturnsNull(): void
    {
        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveByProviderClass(PaginationLimitFixtureProvider::class);

        // Assert
        $this->assertNull($error);
    }

    public function testGivenUnknownClassWhenResolvingThenReturnsNull(): void
    {
        // Arrange
        $resolver = new ProviderNotFoundErrorResolver();

        // Act & Assert
        $this->assertNull($resolver->resolveByResourceClass(static::UNKNOWN_CLASS));
        $this->assertNull($resolver->resolveByProviderClass(static::UNKNOWN_CLASS));
    }

    public function testGivenClassWithoutApiResourceAttributeWhenResolvingByResourceThenReturnsNull(): void
    {
        // Arrange
        $resource = new class {
        };

        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveByResourceClass($resource::class);

        // Assert
        $this->assertNull($error);
    }

    public function testGivenProviderWhoseNotFoundConstantsCannotBeReadWhenResolvingByResourceThenTheErrorSurfaces(): void
    {
        // Arrange
        $resource = new #[ApiResource(provider: MalformedNotFoundErrorFixtureProvider::class)] class {
        };

        // Expect
        $this->expectException(Error::class);

        // Act
        (new ProviderNotFoundErrorResolver())->resolveByResourceClass($resource::class);
    }

    public function testGivenProviderWhoseNotFoundConstantsCannotBeReadWhenResolvingByProviderThenTheErrorSurfaces(): void
    {
        // Expect
        $this->expectException(Error::class);

        // Act
        (new ProviderNotFoundErrorResolver())->resolveByProviderClass(MalformedNotFoundErrorFixtureProvider::class);
    }

    public function testGivenProviderWithTwoNotFoundPairsWhenResolvingByProviderThenTheAmbiguityIsRefusedNamingBothPairs(): void
    {
        // Expect
        $this->expectException(AmbiguousNotFoundErrorDeclarationException::class);
        $this->expectExceptionMessageMatches(sprintf(
            '/%s.*ERROR_MESSAGE_CUSTOMER_NOT_FOUND.*ERROR_MESSAGE_ADDRESS_NOT_FOUND/s',
            preg_quote(AmbiguousNotFoundErrorFixtureProvider::class, '/'),
        ));

        // Act
        (new ProviderNotFoundErrorResolver())->resolveByProviderClass(AmbiguousNotFoundErrorFixtureProvider::class);
    }

    public function testGivenDeclaredPairOnOperationOfAmbiguousProviderWhenResolvingForOperationThenTheDeclarationIsUsedWithoutTouchingTheProvider(): void
    {
        // Arrange
        $operation = new Get(provider: AmbiguousNotFoundErrorFixtureProvider::class, extraProperties: [
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_CODE => static::NOT_FOUND_CODE_CUSTOMER,
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_MESSAGE => static::NOT_FOUND_MESSAGE_CUSTOMER,
        ]);

        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveForOperation($operation);

        // Assert
        $this->assertSame(static::NOT_FOUND_CODE_CUSTOMER, $error['code'] ?? null);
    }

    public function testGivenProviderWithOwnConstantsWhenResolvingForOperationThenTheProviderPairIsUsed(): void
    {
        // Arrange
        $operation = new Get(provider: ErrorResponseFixtureProvider::class);

        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveForOperation($operation);

        // Assert
        $this->assertSame(ErrorResponseFixtureProvider::ERROR_CODE_CATEGORY_NOT_FOUND, $error['code'] ?? null);
    }

    public function testGivenDeclaredPairWhenResolvingForOperationThenItWinsOverTheProviderConstants(): void
    {
        // Arrange
        $operation = new Get(provider: ErrorResponseFixtureProvider::class, extraProperties: [
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_CODE => static::NOT_FOUND_CODE_CUSTOMER,
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_MESSAGE => static::NOT_FOUND_MESSAGE_CUSTOMER,
        ]);

        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveForOperation($operation);

        // Assert
        $this->assertSame([
            'status' => Response::HTTP_NOT_FOUND,
            'detail' => static::NOT_FOUND_MESSAGE_CUSTOMER,
            'message' => static::NOT_FOUND_MESSAGE_CUSTOMER,
            'code' => static::NOT_FOUND_CODE_CUSTOMER,
        ], $error);
    }

    public function testGivenNumericDeclaredCodeWhenResolvingForOperationThenTheCodeIsAString(): void
    {
        // Arrange
        $operation = new Get(extraProperties: [
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_CODE => static::NOT_FOUND_CODE_NUMERIC,
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_MESSAGE => static::NOT_FOUND_MESSAGE_CUSTOMER,
        ]);

        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveForOperation($operation);

        // Assert
        $this->assertSame(static::NOT_FOUND_CODE_CUSTOMER, $error['code'] ?? null);
    }

    public function testGivenCodeWithoutMessageWhenResolvingForOperationThenTheProviderConstantsApply(): void
    {
        // Arrange
        $operation = new Get(provider: ErrorResponseFixtureProvider::class, extraProperties: [
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_CODE => static::NOT_FOUND_CODE_CUSTOMER,
        ]);

        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveForOperation($operation);

        // Assert
        $this->assertSame(ErrorResponseFixtureProvider::ERROR_CODE_CATEGORY_NOT_FOUND, $error['code'] ?? null);
    }

    public function testGivenMessageWithoutCodeAndNoProviderWhenResolvingForOperationThenReturnsNull(): void
    {
        // Arrange
        $operation = new Get(extraProperties: [
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_MESSAGE => static::NOT_FOUND_MESSAGE_CUSTOMER,
        ]);

        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveForOperation($operation);

        // Assert
        $this->assertNull($error);
    }

    public function testGivenOperationWithoutProviderWhenResolvingForOperationThenReturnsNull(): void
    {
        // Act
        $error = (new ProviderNotFoundErrorResolver())->resolveForOperation(new Get());

        // Assert
        $this->assertNull($error);
    }
}
