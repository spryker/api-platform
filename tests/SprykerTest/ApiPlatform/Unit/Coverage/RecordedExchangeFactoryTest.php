<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use ApiPlatform\Validator\Exception\ValidationException;
use Codeception\Test\Unit;
use RuntimeException;
use Spryker\ApiPlatform\Contract\Attribute\Rule;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\RecordedConstraintViolation;
use Spryker\ApiPlatform\Contract\Coverage\RecordedExchange;
use Spryker\ApiPlatform\Exception\LossyIntegerConversionException;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\ApiPlatform\Validation\SynthesizedViolation;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Throwable;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group RecordedExchangeFactoryTest
 * Add your own group annotations below this line
 */
class RecordedExchangeFactoryTest extends Unit
{
    protected const string MEDIA_TYPE_JSON_API = 'application/vnd.api+json';

    public function testGivenAJsonApiWriteWhenRecordingThenTheSentAttributesAndIncludesAreKept(): void
    {
        // Arrange
        $request = $this->createRequest(
            'PATCH',
            '/carts/1?include=items.concrete-products,vouchers',
            ['data' => ['type' => 'carts', 'attributes' => ['currency' => 'EUR', 'name' => 'Mine']]],
        );

        // Act
        $exchange = ContractCoverageFactory::createRecordedExchangeFactory()->create(
            new ApiOperation('PATCH', '/carts/{cartUuid}'),
            $request,
            $this->createResponse(Response::HTTP_OK, ['data' => []]),
        );

        // Assert
        $this->assertSame(['currency' => 'EUR', 'name' => 'Mine'], $exchange->requestAttributes);
        $this->assertSame(['items', 'vouchers'], $exchange->includeRelationshipNames);
        $this->assertSame([], $exchange->errorCodes);
        $this->assertTrue($exchange->isSuccessful());
    }

    public function testGivenAnErrorEnvelopeWhenRecordingThenEveryCodeAndDetailIsKept(): void
    {
        // Arrange
        $response = $this->createResponse(Response::HTTP_UNPROCESSABLE_ENTITY, [
        'errors' => [
            ['code' => '3301', 'status' => 422, 'detail' => 'Cart code not found.'],
            ['code' => '901', 'status' => 422, 'detail' => 'name => This value should not be blank.'],
        ]]);

        // Act
        $exchange = ContractCoverageFactory::createRecordedExchangeFactory()->create(
            new ApiOperation('DELETE', '/carts/{cartUuid}/cart-codes/{code}'),
            $this->createRequest('DELETE', '/carts/1/cart-codes/x'),
            $response,
        );

        // Assert
        $this->assertSame(422, $exchange->status);
        $this->assertSame(['3301', '901'], $exchange->errorCodes);
        $this->assertSame(['Cart code not found.', 'name => This value should not be blank.'], $exchange->errorDetails);
    }

    public function testGivenAValidationExceptionWhenRecordingThenEachViolationIsKeptWithItsRule(): void
    {
        // Arrange
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Blank.', null, [], null, 'items[0].sku', '', null, NotBlank::IS_BLANK_ERROR, new NotBlank()),
            new ConstraintViolation('Long.', null, [], null, 'name', 'x', null, Length::TOO_LONG_ERROR, new Length(max: 1)),
        ]);
        $exception = new RuntimeException('wrapped', 0, new ValidationException($violations));

        // Act
        $exchange = ContractCoverageFactory::createRecordedExchangeFactory()->create(
            new ApiOperation('POST', '/carts'),
            $this->createRequest('POST', '/carts'),
            $this->createResponse(Response::HTTP_UNPROCESSABLE_ENTITY, ['errors' => []]),
            $exception,
        );

        // Assert
        $this->assertEquals(
            [
                new RecordedConstraintViolation('items.sku', 'NotBlank', 'items[0].sku'),
                new RecordedConstraintViolation('name', Rule::LENGTH_MAX->value, 'name'),
            ],
            $exchange->constraintViolations,
        );
    }

    public function testGivenExactLengthViolationsOnBothSidesWhenRecordingThenEachIsKeptWithTheBoundItMissed(): void
    {
        // Arrange
        $exactLength = new Length(exactly: 2);
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Exact.', null, ['{{ value_length }}' => 1, '{{ limit }}' => 2], null, 'iso2Code', 'D', null, Length::NOT_EQUAL_LENGTH_ERROR, $exactLength),
            new ConstraintViolation('Exact.', null, ['{{ value_length }}' => 3, '{{ limit }}' => 2], null, 'iso2Code', 'DEU', null, Length::NOT_EQUAL_LENGTH_ERROR, $exactLength),
        ]);

        // Act
        $exchange = ContractCoverageFactory::createRecordedExchangeFactory()->create(
            new ApiOperation('POST', '/customers/{customerReference}/addresses'),
            $this->createRequest('POST', '/customers/DE--1/addresses'),
            $this->createResponse(Response::HTTP_UNPROCESSABLE_ENTITY, ['errors' => []]),
            new ValidationException($violations),
        );

        // Assert
        $this->assertSame(
            [Rule::LENGTH_MIN->value, Rule::LENGTH_MAX->value],
            array_map(static fn (RecordedConstraintViolation $violation): ?string => $violation->rule, $exchange->constraintViolations),
        );
    }

    public function testGivenAnAuthenticatedRequestDeniedByAnAccessDecisionWhenRecordingThenBothFactsAreKept(): void
    {
        // Arrange
        $request = $this->createRequest('GET', '/customers/DE--1/carts');
        $request->headers->set('Authorization', 'Bearer token');

        // Act
        $exchange = ContractCoverageFactory::createRecordedExchangeFactory()->create(
            new ApiOperation('GET', '/customers/{customerReference}/carts'),
            $request,
            $this->createResponse(Response::HTTP_FORBIDDEN, ['errors' => [['code' => '802', 'status' => 403]]]),
            new AccessDeniedException(),
        );

        // Assert
        $this->assertTrue($exchange->isAccessDenied);
        $this->assertTrue($exchange->isAuthenticated);
    }

    public function testGivenANotFoundWithoutCredentialsWhenRecordingThenNeitherFactIsKept(): void
    {
        // Act
        $exchange = ContractCoverageFactory::createRecordedExchangeFactory()->create(
            new ApiOperation('GET', '/customers/{customerReference}/carts'),
            $this->createRequest('GET', '/customers/DE--1/carts'),
            $this->createResponse(Response::HTTP_NOT_FOUND, ['errors' => []]),
        );

        // Assert
        $this->assertFalse($exchange->isAccessDenied);
        $this->assertFalse($exchange->isAuthenticated);
    }

    public function testGivenANotNormalizableValueExceptionWhenRecordingThenATypeViolationWithTheFullPathIsKept(): void
    {
        // Arrange
        $exception = new BadRequestHttpException('bad', NotNormalizableValueException::createForUnexpectedDataType(
            'Failed to denormalize attribute "quantity".',
            'abc',
            ['int'],
            'productConfigurationInstance.prices.volumePrices.quantity',
        ));

        // Act
        $exchange = $this->recordRejection(['quantity' => 1], $exception);

        // Assert
        $this->assertEquals(
            [new RecordedConstraintViolation('productConfigurationInstance.prices.volumePrices.quantity', 'Type', 'productConfigurationInstance.prices.volumePrices.quantity')],
            $exchange->constraintViolations,
        );
    }

    public function testGivenAConstraintlessInvalidTypeViolationWhenRecordingThenItIsKeptAsAType(): void
    {
        // Arrange
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Wrong type.', null, [], null, 'quantity', 'abc', null, Type::INVALID_TYPE_ERROR),
        ]);

        // Act
        $exchange = $this->recordRejection(['quantity' => 'abc'], new ValidationException($violations));

        // Assert
        $this->assertEquals([new RecordedConstraintViolation('quantity', 'Type', 'quantity')], $exchange->constraintViolations);
    }

    public function testGivenAPropertyAccessTypeErrorWhoseLeafOneSubmittedPathEndsInWhenRecordingThenItsFullPathIsKept(): void
    {
        // Arrange
        $exception = new InvalidArgumentException('Expected argument of type "?int", "string" given at property path "quantity".');

        // Act
        $exchange = $this->recordRejection(['sku' => 'x', 'productConfigurationInstance' => ['prices' => [['volumePrices' => [['quantity' => 'abc']]]]]], $exception);

        // Assert
        $this->assertEquals(
            [new RecordedConstraintViolation('productConfigurationInstance.prices.volumePrices.quantity', 'Type', 'productConfigurationInstance.prices.volumePrices.quantity')],
            $exchange->constraintViolations,
        );
    }

    public function testGivenAPropertyAccessTypeErrorWhoseLeafTwoSubmittedPathsEndInWhenRecordingThenNoViolationIsKept(): void
    {
        // Arrange
        $exception = new InvalidArgumentException('Expected argument of type "?int", "string" given at property path "quantity".');

        // Act
        $exchange = $this->recordRejection(['quantity' => 2, 'productConfigurationInstance' => ['prices' => [['volumePrices' => [['quantity' => 'abc']]]]]], $exception);

        // Assert
        $this->assertSame([], $exchange->constraintViolations);
    }

    public function testGivenALossyIntegerConversionWhenRecordingThenATypeViolationIsKeptForTheSubmittedPath(): void
    {
        // Arrange
        $exception = new LossyIntegerConversionException('quantity');

        // Act
        $exchange = $this->recordRejection(['quantity' => 1.5], $exception);

        // Assert
        $this->assertEquals([new RecordedConstraintViolation('quantity', 'Type', 'quantity')], $exchange->constraintViolations);
    }

    public function testGivenATypeErrorForANestedObjectClassWhenRecordingThenNoViolationIsKept(): void
    {
        // Arrange: the object as a whole failed, which says nothing about which leaf was wrong.
        $exception = new InvalidArgumentException('Expected argument of type "?Generated\\Api\\PaymentSelection", "array" given at property path "paymentSelection".');

        // Act
        $exchange = $this->recordRejection(['paymentSelection' => ['paymentMethodName' => 1]], $exception);

        // Assert
        $this->assertSame([], $exchange->constraintViolations);
    }

    public function testGivenSynthesizedViolationsOnTheRequestWhenRecordingThenEachIsKeptWithItsRuleAndFullPath(): void
    {
        // Arrange
        $request = $this->createRequest('POST', '/carts', ['data' => ['type' => 'carts', 'attributes' => ['billingAddress' => []]]]);
        $request->attributes->set(RequestAttribute::SYNTHESIZED_VIOLATIONS, [
            new SynthesizedViolation('billingAddress.zipCode', 'NotBlank'),
            new SynthesizedViolation('quantity', 'GreaterThan'),
        ]);

        // Act
        $exchange = ContractCoverageFactory::createRecordedExchangeFactory()->create(
            new ApiOperation('POST', '/carts'),
            $request,
            $this->createResponse(Response::HTTP_UNPROCESSABLE_ENTITY, ['errors' => [['code' => '901', 'detail' => 'zipCode => This field is missing.']]]),
        );

        // Assert
        $this->assertEquals(
            [
                new RecordedConstraintViolation('billingAddress.zipCode', 'NotBlank', 'billingAddress.zipCode'),
                new RecordedConstraintViolation('quantity', 'GreaterThan', 'quantity'),
            ],
            $exchange->constraintViolations,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function recordRejection(array $attributes, Throwable $exception): RecordedExchange
    {
        return ContractCoverageFactory::createRecordedExchangeFactory()->create(
            new ApiOperation('POST', '/carts'),
            $this->createRequest('POST', '/carts', ['data' => ['type' => 'carts', 'attributes' => $attributes]]),
            $this->createResponse(Response::HTTP_UNPROCESSABLE_ENTITY, ['errors' => []]),
            $exception,
        );
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function createRequest(string $method, string $uri, ?array $body = null): Request
    {
        $request = Request::create($uri, $method, [], [], [], [], $body === null ? null : (string)json_encode($body));
        if ($body !== null) {
            $request->headers->set('Content-Type', static::MEDIA_TYPE_JSON_API);
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function createResponse(int $status, array $body): Response
    {
        return new Response((string)json_encode($body), $status, ['Content-Type' => static::MEDIA_TYPE_JSON_API]);
    }
}
