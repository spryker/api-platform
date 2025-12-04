<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator\Rules;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Validator\Rules\PropertyValidationRule;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validator
 * @group Rules
 * @group PropertyValidationRuleTest
 * Add your own group annotations below this line
 */
class PropertyValidationRuleTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenValidPropertiesWhenValidatingThenReturnsNoErrors(): void
    {
        // Arrange
        $schema = ['properties' => ['id' => ['type' => 'integer', 'identifier' => true]]];
        $rule = new PropertyValidationRule();

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertEmpty($errors);
    }

    public function testGivenInvalidPropertyNameWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['invalid-name' => ['type' => 'string']]];
        $rule = new PropertyValidationRule();

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenInvalidPropertyTypeWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['name' => ['type' => 'invalid', 'identifier' => true]]];
        $rule = new PropertyValidationRule();

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenNoIdentifierPropertyWhenValidatingThenReturnsError(): void
    {
        $this->markTestSkipped('Identifier property is optional for now.');

        // Arrange
        $schema = ['properties' => ['name' => ['type' => 'string']]];
        $rule = new PropertyValidationRule();

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenNonBooleanAttributeWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['id' => ['type' => 'integer', 'identifier' => 'yes']]];
        $rule = new PropertyValidationRule();

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenIncompatibleDefaultValueWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['count' => ['type' => 'integer', 'default' => 'invalid', 'identifier' => true]]];
        $rule = new PropertyValidationRule();

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenNonArrayOpenapiContextWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['id' => ['type' => 'integer', 'openapiContext' => 'invalid', 'identifier' => true]]];
        $rule = new PropertyValidationRule();

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }
}
