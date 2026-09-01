<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\NestedObjectClassGenerator;
use Spryker\ApiPlatform\Generator\PropertyAttributeGenerator;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group NestedObjectClassGeneratorTest
 * Add your own group annotations below this line
 */
class NestedObjectClassGeneratorTest extends Unit
{
    public function testGivenNestedPropertiesWhenGeneratingThenReturnsTypedNestedObjectClass(): void
    {
        // Arrange
        $generator = new NestedObjectClassGenerator(new PropertyAttributeGenerator(), new PhpTemplateRenderer());
        $properties = [
            'grandTotal' => ['name' => 'grandTotal', 'type' => 'integer', 'description' => 'Final total'],
        ];

        // Act — pass owner resource name so the class lands in the per-resource sub-namespace.
        $classes = $generator->generate('CartsTotals', $properties, 'Storefront', ['carts.resource.yml'], false, 'Carts');

        // Assert — classes are keyed by the full class name `{Base}{ApiType}Object`.
        $this->assertArrayHasKey('CartsTotalsStorefrontObject', $classes);
        $this->assertStringContainsString('final class CartsTotalsStorefrontObject', $classes['CartsTotalsStorefrontObject']);
        $this->assertStringContainsString('namespace Generated\Api\Storefront\Carts;', $classes['CartsTotalsStorefrontObject']);
        $this->assertStringContainsString('public ?int $grandTotal = null;', $classes['CartsTotalsStorefrontObject']);
        $this->assertStringContainsString("description: 'Final total'", $classes['CartsTotalsStorefrontObject']);
        $this->assertStringNotContainsString('#[ApiResource', $classes['CartsTotalsStorefrontObject']);
        // The #[ApiProperty] attribute must be imported, otherwise it resolves to a
        // non-existent class in the Generated namespace and attribute reflection fails.
        $this->assertStringContainsString('use ApiPlatform\Metadata\ApiProperty;', $classes['CartsTotalsStorefrontObject']);
    }

    public function testGivenPlainPropertyWithoutAttributesWhenGeneratingThenAddsNoUnusedImports(): void
    {
        // Arrange
        $generator = new NestedObjectClassGenerator(new PropertyAttributeGenerator(), new PhpTemplateRenderer());
        $properties = [
            'plain' => ['name' => 'plain', 'type' => 'string'],
        ];

        // Act — pass owner resource name so the class lands in the per-resource sub-namespace.
        $classes = $generator->generate('CartsPlain', $properties, 'Storefront', ['carts.resource.yml'], false, 'Carts');

        // Assert — no #[ApiProperty] emitted for an attribute-less property, so no unused import.
        $this->assertStringNotContainsString('use ApiPlatform\Metadata\ApiProperty;', $classes['CartsPlainStorefrontObject']);
    }

    public function testGivenNestedObjectPropertyWhenGeneratingThenEmitsChildClassAndTypesParent(): void
    {
        // Arrange
        $generator = new NestedObjectClassGenerator(new PropertyAttributeGenerator(), new PhpTemplateRenderer());
        $properties = [
            'tax' => [
                'name' => 'tax',
                'type' => 'object',
                'properties' => ['amount' => ['name' => 'amount', 'type' => 'integer']],
            ],
        ];

        // Act — pass owner 'Carts'; child classes must share the same owner namespace.
        $classes = $generator->generate('CartsTotals', $properties, 'Storefront', ['carts.resource.yml'], false, 'Carts');

        // Assert — the nested `tax` object becomes its own child class `CartsTotalsTax{ApiType}Object`,
        // and the parent property is typed to that full class name.
        $this->assertArrayHasKey('CartsTotalsStorefrontObject', $classes);
        $this->assertArrayHasKey('CartsTotalsTaxStorefrontObject', $classes);
        $this->assertStringContainsString('public ?CartsTotalsTaxStorefrontObject $tax = null;', $classes['CartsTotalsStorefrontObject']);
        $this->assertStringContainsString('public ?int $amount = null;', $classes['CartsTotalsTaxStorefrontObject']);
    }

    public function testGivenObjectCollectionWithOwnerWhenGeneratingThenEmitsFullyQualifiedCollectionPhpDoc(): void
    {
        // Arrange
        $generator = new NestedObjectClassGenerator(new PropertyAttributeGenerator(), new PhpTemplateRenderer());
        $properties = [
            'customer' => [
                'name' => 'customer',
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['id' => ['name' => 'id', 'type' => 'string']],
                ],
            ],
        ];

        // Act — owner 'Carts' → sub-namespace Generated\Api\Storefront\Carts.
        $classes = $generator->generate('Carts', $properties, 'Storefront', ['carts.resource.yml'], false, 'Carts');

        // Assert — parent property @var uses the fully-qualified owner-qualified child class name.
        $this->assertArrayHasKey('CartsStorefrontObject', $classes);
        $this->assertArrayHasKey('CartsCustomersStorefrontObject', $classes);
        $this->assertStringContainsString(
            '@var array<int, \Generated\Api\Storefront\Carts\CartsCustomersStorefrontObject>',
            $classes['CartsStorefrontObject'],
        );
    }

    public function testGivenObjectCollectionInsideObjectWhenGeneratingThenEmitsVarDocblock(): void
    {
        // Arrange
        $generator = new NestedObjectClassGenerator(new PropertyAttributeGenerator(), new PhpTemplateRenderer());
        $properties = [
            'availabilities' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'availableQuantity' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        // Act
        $classes = $generator->generate('ProductsStocks', $properties, 'Backend', ['products.resource.yml'], false, 'Products');

        // Assert — the parent value object keeps a bare array and names its element class; the child
        // value object is emitted in the same per-owner sub-namespace.
        $parent = $classes['ProductsStocksBackendObject'];
        $this->assertStringContainsString('public array $availabilities = [];', $parent);
        $this->assertStringContainsString(
            '@var array<int, \Generated\Api\Backend\Products\ProductsStocksAvailabilitiesBackendObject>',
            $parent,
        );
        $this->assertStringNotContainsString('#[CollectionOf(', $parent);
        $this->assertArrayHasKey('ProductsStocksAvailabilitiesBackendObject', $classes);
    }
}
