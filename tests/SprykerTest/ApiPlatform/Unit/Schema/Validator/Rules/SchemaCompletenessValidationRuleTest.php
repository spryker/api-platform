<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator\Rules;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Validator\Rules\SchemaCompletenessValidationRule;
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
 * @group SchemaCompletenessValidationRuleTest
 * Add your own group annotations below this line
 */
class SchemaCompletenessValidationRuleTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenSchemaWithOnlyOperationsWhenValidatingThenReturnsNoErrors(): void
    {
        $rule = new SchemaCompletenessValidationRule();

        $errors = $rule->validate([
            'sourceFile' => 'orders.resource.yml',
            'operations' => [['type' => 'Get'], ['type' => 'GetCollection']],
        ]);

        $this->assertSame([], $errors);
    }

    public function testGivenSchemaWithOnlyPropertiesWhenValidatingThenReturnsNoErrors(): void
    {
        $rule = new SchemaCompletenessValidationRule();

        $errors = $rule->validate([
            'sourceFile' => 'shipments.resource.yml',
            'properties' => ['shipmentsId' => ['type' => 'string', 'identifier' => true]],
        ]);

        $this->assertSame([], $errors);
    }

    public function testGivenSchemaWithOnlyIncludesWhenValidatingThenReturnsNoErrors(): void
    {
        $rule = new SchemaCompletenessValidationRule();

        $errors = $rule->validate([
            'sourceFile' => 'aggregator.resource.yml',
            'includes' => [['relationshipName' => 'orders', 'targetResource' => 'Orders']],
        ]);

        $this->assertSame([], $errors);
    }

    public function testGivenSchemaWithOperationsAndPropertiesAndIncludesWhenValidatingThenReturnsNoErrors(): void
    {
        $rule = new SchemaCompletenessValidationRule();

        $errors = $rule->validate([
            'sourceFile' => 'orders.resource.yml',
            'operations' => [['type' => 'Get']],
            'properties' => ['orderReference' => ['type' => 'string']],
            'includes' => [['relationshipName' => 'merchants', 'targetResource' => 'Merchants']],
        ]);

        $this->assertSame([], $errors);
    }

    public function testGivenSchemaWithNoOperationsNoPropertiesNoIncludesWhenValidatingThenReturnsCompletenessError(): void
    {
        $rule = new SchemaCompletenessValidationRule();

        $errors = $rule->validate([
            'sourceFile' => 'empty.resource.yml',
        ]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('must define at least one of: operations, properties, includes', $errors[0]);
        $this->assertStringContainsString('empty.resource.yml', $errors[0]);
    }

    public function testGivenSchemaWithAllThreeKeysButEmptyWhenValidatingThenReturnsCompletenessError(): void
    {
        $rule = new SchemaCompletenessValidationRule();

        $errors = $rule->validate([
            'sourceFile' => 'empty.resource.yml',
            'operations' => [],
            'properties' => [],
            'includes' => [],
        ]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('must define at least one of', $errors[0]);
    }

    public function testGivenInvalidOperationTypeWhenValidatingThenReturnsOperationTypeError(): void
    {
        $rule = new SchemaCompletenessValidationRule();

        $errors = $rule->validate([
            'sourceFile' => 'orders.resource.yml',
            'operations' => [['type' => 'Yeet']],
        ]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Invalid operation type "Yeet"', $errors[0]);
        $this->assertStringContainsString('orders.resource.yml', $errors[0]);
    }

    public function testGivenValidAndInvalidOperationTypesWhenValidatingThenReturnsOnlyInvalidOnes(): void
    {
        $rule = new SchemaCompletenessValidationRule();

        $errors = $rule->validate([
            'sourceFile' => 'orders.resource.yml',
            'operations' => [['type' => 'Get'], ['type' => 'Frobnicate'], ['type' => 'Post']],
        ]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Invalid operation type "Frobnicate"', $errors[0]);
    }
}
