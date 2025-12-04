<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Schema\Validator\Rules\MergeValidationRule;
use Spryker\ApiPlatform\Schema\Validator\SchemaValidator;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validator
 * @group SchemaValidatorTest
 * Add your own group annotations below this line
 */
class SchemaValidatorTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenValidMergedSchemaWhenValidatingPostMergeThenSucceeds(): void
    {
        // Arrange
        $schema = ['shortName' => 'Customer', 'properties' => []];
        $validator = new SchemaValidator([], $this->createMergeValidationRule());

        // Act & Assert
        $validator->validatePostMerge($schema);
        $this->assertTrue(true);
    }

    public function testGivenInvalidMergedSchemaWhenValidatingPostMergeThenThrowsException(): void
    {
        // Arrange
        $schema = ['invalid' => 'data'];
        $validator = new SchemaValidator([], $this->createMergeValidationRule());

        // Expect
        $this->expectException(ApiSchemaValidationException::class);

        // Act
        $validator->validatePostMerge($schema);
    }

    protected function createMergeValidationRule(): MergeValidationRule
    {
        return new MergeValidationRule(PropertyAccess::createPropertyAccessor());
    }
}
