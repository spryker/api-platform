<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator\Rules;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Validator\Rules\OperationTypeReferenceValidationRule;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validator
 * @group Rules
 * @group OperationTypeReferenceValidationRuleTest
 * Add your own group annotations below this line
 */
class OperationTypeReferenceValidationRuleTest extends Unit
{
    public function testGivenWritableOnADeclaredWriteWhenValidatingThenNoErrorIsReported(): void
    {
        // Arrange
        $schema = [
            'operations' => ['Post' => ['type' => 'Post'], 'Patch' => ['type' => 'Patch']],
            'properties' => ['store' => ['writableOn' => ['Post']]],
        ];

        // Act
        $errors = (new OperationTypeReferenceValidationRule())->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenWritableOnAnUndeclaredOrReadOperationWhenValidatingThenEachIsReported(): void
    {
        // Arrange
        $schema = [
            'sourceFile' => 'guest-carts.resource.yml',
            'operations' => ['Get' => ['type' => 'Get'], 'Patch' => ['type' => 'Patch']],
            'properties' => ['store' => ['writableOn' => ['Post', 'Get']]],
        ];

        // Act
        $errors = (new OperationTypeReferenceValidationRule())->validate($schema);

        // Assert
        $this->assertSame([
            'Property "store" in guest-carts.resource.yml is writableOn "Post", which is not a write operation the resource declares (declared: Patch).',
            'Property "store" in guest-carts.resource.yml is writableOn "Get", which is not a write operation the resource declares (declared: Patch).',
        ], $errors);
    }

    public function testGivenIncludedOnAnUndeclaredOperationWhenValidatingThenItIsReported(): void
    {
        // Arrange
        $schema = [
            'sourceFile' => 'carts.resource.yml',
            'operations' => ['Get' => ['type' => 'Get']],
            'includes' => [['relationshipName' => 'vouchers', 'includedOn' => ['GetCollection']]],
        ];

        // Act
        $errors = (new OperationTypeReferenceValidationRule())->validate($schema);

        // Assert
        $this->assertSame(['Include "vouchers" in carts.resource.yml is includedOn "GetCollection", which is not an operation the resource declares (declared: Get).'], $errors);
    }

    public function testGivenANestedPropertyWritableOnAnUndeclaredWriteWhenValidatingThenItIsReportedWithItsPath(): void
    {
        // Arrange
        $schema = [
            'sourceFile' => 'checkout.resource.yml',
            'operations' => ['Post' => ['type' => 'Post']],
            'properties' => [
                'address' => ['properties' => ['zip' => ['writableOn' => ['Pacth']]]],
                'items' => ['items' => ['properties' => ['sku' => ['writableOn' => ['Patch']]]]],
            ],
        ];

        // Act
        $errors = (new OperationTypeReferenceValidationRule())->validate($schema);

        // Assert
        $this->assertSame([
            'Property "address.zip" in checkout.resource.yml is writableOn "Pacth", which is not a write operation the resource declares (declared: Post).',
            'Property "items.sku" in checkout.resource.yml is writableOn "Patch", which is not a write operation the resource declares (declared: Post).',
        ], $errors);
    }

    public function testGivenAScalarWritableOnWhenValidatingThenItIsReportedAsNotAList(): void
    {
        // Arrange
        $schema = [
            'sourceFile' => 'carts.resource.yml',
            'operations' => ['Post' => ['type' => 'Post']],
            'properties' => ['store' => ['writableOn' => 'Post']],
        ];

        // Act
        $errors = (new OperationTypeReferenceValidationRule())->validate($schema);

        // Assert
        $this->assertSame(['Property "store" in carts.resource.yml declares writableOn, which must be a list of operation types.'], $errors);
    }

    public function testGivenAScalarIncludedOnWhenValidatingThenItIsReportedAsNotAList(): void
    {
        // Arrange
        $schema = [
            'sourceFile' => 'carts.resource.yml',
            'operations' => ['Get' => ['type' => 'Get']],
            'includes' => [['relationshipName' => 'vouchers', 'includedOn' => 'Get']],
        ];

        // Act
        $errors = (new OperationTypeReferenceValidationRule())->validate($schema);

        // Assert
        $this->assertSame(['Include "vouchers" in carts.resource.yml declares includedOn, which must be a list of operation types.'], $errors);
    }
}
