<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Attribute\Rule;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Spryker\ApiPlatform\Contract\Coverage\RecordedConstraintViolation;
use Spryker\ApiPlatform\Contract\Coverage\RecordedExchange;
use Spryker\ApiPlatform\Contract\Coverage\ValidationConstraint;
use Spryker\ApiPlatform\Contract\Coverage\ValidationEvidenceVerifier;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ValidationEvidenceVerifierTest
 * Add your own group annotations below this line
 */
class ValidationEvidenceVerifierTest extends Unit
{
    protected const string VERB = 'PATCH';

    protected const string URI_TEMPLATE = '/carts/{cartUuid}';

    public function testGivenAViolationWithTheDeclaredAttributeAndRuleWhenVerifyingThenTheDeclarationIsVerified(): void
    {
        // Arrange
        $exchange = $this->createRejection([new RecordedConstraintViolation('name', 'NotBlank')]);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('name', 'NotBlank')], [$exchange]);

        // Assert
        $this->assertSame([], $unverified);
    }

    public function testGivenAViolationWithTheDeclaredAttributeButAnotherRuleWhenVerifyingThenTheDeclarationIsUnverified(): void
    {
        // Arrange
        $exchange = $this->createRejection([new RecordedConstraintViolation('name', Rule::LENGTH_MAX->value)], ['name => This value is too long.']);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('name', 'NotBlank')], [$exchange]);

        // Assert
        $this->assertCount(1, $unverified);
    }

    public function testGivenALengthTooLongViolationWhenVerifyingALengthMaxDeclarationThenItIsVerified(): void
    {
        // Arrange
        $exchange = $this->createRejection([new RecordedConstraintViolation('name', Rule::LENGTH_MAX->value)]);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('name', Rule::LENGTH_MAX->value)], [$exchange]);

        // Assert
        $this->assertSame([], $unverified);
    }

    public function testGivenAViolationOnAMapEntryWhenVerifyingADeclarationOnTheMapThenItIsVerified(): void
    {
        // Arrange
        $exchange = $this->createRejection([new RecordedConstraintViolation('unitPriceMap.any-group-key', 'PositiveOrZero', 'unitPriceMap[any-group-key]')]);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('unitPriceMap', 'PositiveOrZero')], [$exchange]);

        // Assert
        $this->assertSame([], $unverified);
    }

    public function testGivenADetailNamingTheAttributeWithoutAStructuredViolationWhenVerifyingThenTheDeclarationIsUnverified(): void
    {
        // Arrange
        $exchange = $this->createRejection([], ['priceMode => This value should be of type string.']);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('priceMode', 'Type')], [$exchange]);

        // Assert
        $this->assertCount(1, $unverified);
    }

    public function testGivenASynthesizedTypeViolationWhenVerifyingAGreaterThanDeclarationThenTheDeclarationIsUnverified(): void
    {
        // Arrange
        $exchange = $this->createRejection([new RecordedConstraintViolation('quantity', 'Type', 'quantity')], ['quantity => This value should be of type numeric.']);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('quantity', 'GreaterThan')], [$exchange]);

        // Assert
        $this->assertCount(1, $unverified);
    }

    public function testGivenATopLevelViolationWithTheSameLeafWhenVerifyingANestedDeclarationThenTheDeclarationIsUnverified(): void
    {
        // Arrange
        $exchange = $this->createRejection([new RecordedConstraintViolation('quantity', 'Type', 'quantity')]);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('productConfigurationInstance.prices.volumePrices.quantity', 'Type')], [$exchange]);

        // Assert
        $this->assertCount(1, $unverified);
    }

    public function testGivenAViolationOnASiblingWithTheSameLeafWhenVerifyingANestedDeclarationThenTheDeclarationIsUnverified(): void
    {
        // Arrange
        $exchange = $this->createRejection([new RecordedConstraintViolation('shippingAddress.zipCode', 'NotBlank', 'shippingAddress.zipCode')]);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('billingAddress.zipCode', 'NotBlank')], [$exchange]);

        // Assert
        $this->assertCount(1, $unverified);
    }

    public function testGivenASynthesizedNestedViolationWithTheFullPathWhenVerifyingThenTheDeclarationIsVerified(): void
    {
        // Arrange: the detail names only the leaf, the synthesized violation keeps the full path.
        $exchange = $this->createRejection(
            [new RecordedConstraintViolation('productConfigurationInstance.isComplete', 'Type', 'productConfigurationInstance.isComplete')],
            ['isComplete => This value should be of type bool.'],
        );

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('productConfigurationInstance.isComplete', 'Type')], [$exchange]);

        // Assert
        $this->assertSame([], $unverified);
    }

    public function testGivenOnlyASuccessOnTheBoundOperationWhenVerifyingThenTheDeclarationIsUnverified(): void
    {
        // Arrange
        $exchange = new RecordedExchange(new ApiOperation(static::VERB, static::URI_TEMPLATE), 200);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('name', 'NotBlank')], [$exchange]);

        // Assert
        $this->assertCount(1, $unverified);
    }

    public function testGivenA422OnAnotherOperationWhenVerifyingThenTheDeclarationIsUnverified(): void
    {
        // Arrange
        $exchange = new RecordedExchange(new ApiOperation('POST', '/carts'), 422, constraintViolations: [new RecordedConstraintViolation('name', 'NotBlank')]);

        // Act
        $unverified = (new ValidationEvidenceVerifier())->verify([$this->createDeclaration('name', 'NotBlank')], [$exchange]);

        // Assert
        $this->assertCount(1, $unverified);
    }

    protected function createDeclaration(string $attribute, string $rule): ValidationConstraint
    {
        return new ValidationConstraint('carts', $attribute, $rule, static::VERB, static::URI_TEMPLATE);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\RecordedConstraintViolation> $violations
     * @param array<string> $details
     */
    protected function createRejection(array $violations, array $details = []): RecordedExchange
    {
        return new RecordedExchange(
            new ApiOperation(static::VERB, static::URI_TEMPLATE),
            422,
            errorCodes: ['901'],
            errorDetails: $details,
            constraintViolations: $violations,
        );
    }
}
