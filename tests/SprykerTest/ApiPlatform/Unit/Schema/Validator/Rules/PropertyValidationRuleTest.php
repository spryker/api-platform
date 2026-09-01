<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validator\Rules;

use Codeception\Test\Unit;
use ReflectionMethod;
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
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertEmpty($errors);
    }

    public function testGivenInvalidPropertyNameWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['invalid-name' => ['type' => 'string']]];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenInvalidPropertyTypeWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['name' => ['type' => 'invalid', 'identifier' => true]]];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenResourceClassNameTypeWhenValidatingThenReturnsNoErrors(): void
    {
        // Arrange
        $schema = [
            'properties' => [
                'customer' => ['type' => 'CustomersStorefrontResource'],
                'product' => ['type' => 'ProductsBackendResource'],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertEmpty($errors);
    }

    public function testGivenNoIdentifierPropertyWhenValidatingThenReturnsError(): void
    {
        $this->markTestSkipped('Identifier property is optional for now.');

        // Arrange
        $schema = ['properties' => ['name' => ['type' => 'string']]];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenNonBooleanAttributeWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['id' => ['type' => 'integer', 'identifier' => 'yes']]];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenIncompatibleDefaultValueWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['count' => ['type' => 'integer', 'default' => 'invalid', 'identifier' => true]]];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenNonArrayOpenapiContextWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = ['properties' => ['id' => ['type' => 'integer', 'openapiContext' => 'invalid', 'identifier' => true]]];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    public function testGivenPropertyWithBothItemsAndOpenapiContextItemsWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = [
            'sourceFile' => 'products.resource.yml',
            'properties' => [
                'prices' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['grossAmount' => ['type' => 'integer']]],
                    'openapiContext' => ['items' => ['type' => 'object']],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('prices', $errors[0]);
        $this->assertStringContainsString('openapiContext.items', $errors[0]);
    }

    public function testGivenNestedPropertyWithBothItemsAndOpenapiContextItemsWhenValidatingThenReturnsError(): void
    {
        // Arrange
        $schema = [
            'sourceFile' => 'products.resource.yml',
            'properties' => [
                'stocks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'availabilities' => [
                                'type' => 'array',
                                'items' => ['type' => 'object', 'properties' => ['quantity' => ['type' => 'integer']]],
                                'openapiContext' => ['items' => ['type' => 'object']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('stocks.items.availabilities', $errors[0]);
    }

    public function testGivenPropertyWithOnlyOpenapiContextItemsWhenValidatingThenReturnsNoError(): void
    {
        // Arrange — the hand-written form is still legal on its own; that is the Storefront status quo.
        $schema = [
            'sourceFile' => 'merchants.resource.yml',
            'properties' => [
                'categories' => [
                    'type' => 'array',
                    'openapiContext' => ['items' => ['type' => 'object']],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenPropertyWithOnlyItemsAtTopLevelWhenValidatingThenReturnsNoError(): void
    {
        // Arrange — the typed form is the shape this branch introduces; it must stay legal on its own.
        $schema = [
            'sourceFile' => 'products.resource.yml',
            'properties' => [
                'prices' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenPropertyWithSiblingItemsAndWinningRelationshipIncludeWhenValidatingThenReturnsError(): void
    {
        // Arrange — the relationship phpDoc would win (Task 7B's exact collision scenario), so the
        // "items" typed docblock would be silently dropped by ClassGenerator::transformProperties().
        $schema = [
            'sourceFile' => 'customers.resource.yml',
            'includes' => [
                ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
            ],
            'properties' => [
                'addresses' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string']]],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('addresses', $errors[0]);
    }

    public function testGivenKebabCasedRelationshipCollisionWhenValidatingThenErrorNamesTheAuthoredIncludesEntry(): void
    {
        // Arrange — the property is camelCase and the colliding entry is kebab-case, so naming the
        // property alone leaves the author to re-derive the match by hand across every include. The
        // error must quote the entry as it is authored, which is the string they can actually grep for.
        $schema = [
            'sourceFile' => 'customers.resource.yml',
            'includes' => [
                ['relationshipName' => 'company-business-units', 'targetResource' => 'CompanyBusinessUnits'],
                ['relationshipName' => 'shipping-addresses', 'targetResource' => 'CustomersShippingAddresses'],
            ],
            'properties' => [
                'shippingAddresses' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string']]],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('shipping-addresses', $errors[0]);
        $this->assertStringContainsString('CustomersShippingAddresses', $errors[0]);
        // The non-colliding entry must not be named, or the message points at the wrong fix.
        $this->assertStringNotContainsString('company-business-units', $errors[0]);
    }

    public function testGivenRelationshipCollisionWithoutATargetResourceWhenValidatingThenErrorStillNamesTheRelationship(): void
    {
        // Arrange — `targetResource` is not guaranteed present on an include entry.
        $schema = [
            'sourceFile' => 'customers.resource.yml',
            'includes' => [['relationshipName' => 'shipping-addresses']],
            'properties' => [
                'shippingAddresses' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string']]],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('shipping-addresses', $errors[0]);
    }

    public function testGivenPropertyNamedInIncludesWithNoSiblingItemsWhenValidatingThenReturnsNoError(): void
    {
        // Arrange — the overwhelmingly common case: a plain relationship property must stay silent.
        $schema = [
            'sourceFile' => 'customers.resource.yml',
            'includes' => [
                ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
            ],
            'properties' => [
                'addresses' => ['type' => 'array', 'writable' => false, 'readable' => false],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenPropertyWithSiblingItemsNotNamedInAnyIncludeWhenValidatingThenReturnsNoError(): void
    {
        // Arrange — the new typed form with no matching relationship must stay legal.
        $schema = [
            'sourceFile' => 'products.resource.yml',
            'includes' => [
                ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
            ],
            'properties' => [
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenPropertyWithSiblingItemsAndResolverBasedIncludeWhenValidatingThenReturnsNoError(): void
    {
        // Arrange — a resolver-based include never wins the phpDoc slot, so the docblock is not lost.
        $schema = [
            'sourceFile' => 'customers.resource.yml',
            'includes' => [
                [
                    'relationshipName' => 'addresses',
                    'targetResource' => 'CustomersAddresses',
                    'resolverClass' => 'Pyz\\Glue\\Customers\\Resolver\\AddressesResolver',
                ],
            ],
            'properties' => [
                'addresses' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string']]],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenPropertyWithSiblingItemsAndMatchingIncludeButWritableWhenValidatingThenReturnsNoError(): void
    {
        // Arrange — a writable property never wins the phpDoc slot, so the docblock is not lost.
        $schema = [
            'sourceFile' => 'customers.resource.yml',
            'includes' => [
                ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
            ],
            'properties' => [
                'addresses' => [
                    'type' => 'array',
                    'writable' => true,
                    'items' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string']]],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenPropertyWithSiblingItemsAndMatchingIncludeButTruthyNonBooleanWritableWhenValidatingThenReturnsNoError(): void
    {
        // Arrange — RelationshipPhpDocGenerator::generate() disqualifies "writable" on any truthy
        // value (`$property['writable'] ?? false`), not just strict `=== true`. The mirror must match
        // that truthy semantics exactly, or a non-boolean-but-truthy "writable" would be a false positive.
        // A non-boolean "writable" is independently rejected by validateBooleanAttributes() in the same
        // validate() pass, which makes this scenario unreachable through the public entry point — so the
        // contract is pinned by invoking the protected collision-check method directly, bypassing the
        // unrelated boolean-type check.
        $schema = [
            'sourceFile' => 'customers.resource.yml',
            'includes' => [
                ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
            ],
        ];
        $properties = [
            'addresses' => [
                'type' => 'array',
                'writable' => 1,
                'items' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string']]],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);
        $method = new ReflectionMethod(PropertyValidationRule::class, 'validateRelationshipItemsCollision');

        // Act
        $errors = $method->invoke($rule, $properties, $schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenPropertyWithSiblingItemsAndMatchingIncludeButReadableWhenValidatingThenReturnsNoError(): void
    {
        // Arrange — a readable:true attribute property never wins the phpDoc slot, so the docblock is not lost.
        $schema = [
            'sourceFile' => 'customers.resource.yml',
            'includes' => [
                ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
            ],
            'properties' => [
                'addresses' => [
                    'type' => 'array',
                    'readable' => true,
                    'items' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string']]],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertSame([], $errors);
    }

    public function testGivenKebabCaseIncludeMatchingCamelCasePropertyWithSiblingItemsWhenValidatingThenReturnsError(): void
    {
        // Arrange — pins the kebabToCamelCase half of the mirrored predicate.
        $schema = [
            'sourceFile' => 'products.resource.yml',
            'includes' => [
                ['relationshipName' => 'product-prices', 'targetResource' => 'ProductPrices'],
            ],
            'properties' => [
                'productPrices' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['grossAmount' => ['type' => 'integer']]],
                ],
            ],
        ];
        $rule = $this->tester->getContainer()->get(PropertyValidationRule::class);

        // Act
        $errors = $rule->validate($schema);

        // Assert
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('productPrices', $errors[0]);
    }
}
