<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator\Rules;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\DeclaredErrorCodeResolver;
use Spryker\ApiPlatform\Schema\Validator\Rules\ErrorCodesValidationRule;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validator
 * @group Rules
 * @group ErrorCodesValidationRuleTest
 * Add your own group annotations below this line
 */
class ErrorCodesValidationRuleTest extends Unit
{
    public function testGivenValidCodeDeclarationsWhenValidatingThenNoErrorIsReported(): void
    {
        // Arrange
        $schema = [
            'shortName' => 'carts',
            'sourceFile' => 'carts.resource.yml',
            'commonErrorCodes' => [['status' => 422, 'code' => '901']],
            'operations' => ['Patch' => ['openapiContext' => ['responses' => [422 => ['description' => 'Rejected.', 'codes' => ['3301']]]]]],
        ];

        // Act
        $errors = (new ErrorCodesValidationRule(new DeclaredErrorCodeResolver()))->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenSeveralBrokenDeclarationsWhenValidatingThenEachOneIsReported(): void
    {
        // Arrange
        $schema = [
            'shortName' => 'carts',
            'sourceFile' => 'carts.resource.yml',
            'commonErrorCodes' => [['code' => '901']],
            'operations' => [
                'Patch' => ['openapiContext' => ['responses' => [200 => ['description' => 'OK.', 'codes' => ['3301']]]]],
                'Post' => ['openapiContext' => ['responses' => [422 => ['description' => 'Rejected.', 'codes' => [901]]]]],
            ],
        ];

        // Act
        $errors = (new ErrorCodesValidationRule(new DeclaredErrorCodeResolver()))->validate($schema);

        // Assert
        $this->assertCount(3, $errors);
        $this->assertStringContainsString('commonErrorCodes entry #0 of carts.resource.yml needs an error "status"', $errors[0]);
        $this->assertStringContainsString('openapiContext.responses.200 of carts Patch (carts.resource.yml) declares codes', $errors[1]);
        $this->assertStringContainsString('declares no string "code"', $errors[2]);
    }

    public function testGivenCommonErrorCodeWhoseStatusNoOperationDeclaresWhenValidatingThenItIsReported(): void
    {
        // Arrange
        $schema = [
            'shortName' => 'carts',
            'sourceFile' => 'carts.resource.yml',
            'commonErrorCodes' => [['status' => 403, 'code' => '901'], ['status' => 422, 'code' => '902']],
            'operations' => ['Patch' => ['openapiContext' => ['responses' => [422 => ['description' => 'Rejected.', 'codes' => ['3301']]]]]],
        ];

        // Act
        $errors = (new ErrorCodesValidationRule(new DeclaredErrorCodeResolver()))->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('commonErrorCodes of carts.resource.yml declare 901 for status 403, but no operation of the merged resource declares codes on its 403 response', $errors[0]);
    }

    public function testGivenCommonErrorCodeWhoseStatusIsDeclaredWithoutCodesWhenValidatingThenItIsReported(): void
    {
        // Arrange: a project operation replaced the core one and kept the 403 response but not its codes.
        $schema = [
            'shortName' => 'carts',
            'sourceFile' => 'carts.resource.yml',
            'commonErrorCodes' => [['status' => 403, 'code' => '901']],
            'operations' => ['Post' => ['openapiContext' => ['responses' => [403 => ['description' => 'Forbidden.']]]]],
        ];

        // Act
        $errors = (new ErrorCodesValidationRule(new DeclaredErrorCodeResolver()))->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('declare 901 for status 403', $errors[0]);
    }

    public function testGivenCommonErrorCodeWhoseStatusOneOperationDeclaresWithCodesWhenValidatingThenNoErrorIsReported(): void
    {
        // Arrange
        $schema = [
            'shortName' => 'carts',
            'sourceFile' => 'carts.resource.yml',
            'commonErrorCodes' => [['status' => 403, 'code' => '901']],
            'operations' => [
                'Get' => ['openapiContext' => ['responses' => [404 => ['description' => 'Not found.', 'codes' => ['101']]]]],
                'Patch' => ['openapiContext' => ['responses' => [403 => ['description' => 'Forbidden.', 'codes' => ['3302']]]]],
            ],
        ];

        // Act
        $errors = (new ErrorCodesValidationRule(new DeclaredErrorCodeResolver()))->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenCommonErrorCodeOnAStatusWhoseCodesAreMalformedWhenValidatingThenOnlyTheMalformedCodesAreReported(): void
    {
        // Arrange
        $schema = [
            'shortName' => 'carts',
            'sourceFile' => 'carts.resource.yml',
            'commonErrorCodes' => [['status' => 422, 'code' => '901']],
            'operations' => ['Patch' => ['openapiContext' => ['responses' => [422 => ['description' => 'Rejected.', 'codes' => [3301]]]]]],
        ];

        // Act
        $errors = (new ErrorCodesValidationRule(new DeclaredErrorCodeResolver()))->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('declares no string "code"', $errors[0]);
    }
}
