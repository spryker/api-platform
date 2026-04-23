<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\RelationshipPhpDocGenerator;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group RelationshipPhpDocGeneratorTest
 * Add your own group annotations below this line
 */
class RelationshipPhpDocGeneratorTest extends Unit
{
    protected RelationshipPhpDocGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = $this->tester->getContainer()->get(RelationshipPhpDocGenerator::class);
    }

    public function testGivenPropertyWithMatchingIncludeWhenGeneratingThenReturnsPhpDocWithFqcn(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'addresses';
        $includes = [
            ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
        ];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('@var \Generated\Api\Storefront\CustomersAddressesStorefrontResource[]', $result);
    }

    public function testGivenPropertyWithoutMatchingIncludeWhenGeneratingThenReturnsEmptyString(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'addresses';
        $includes = [
            ['relationshipName' => 'orders', 'targetResource' => 'CustomersOrders'],
        ];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('', $result);
    }

    public function testGivenNonArrayPropertyWithIncludeWhenGeneratingThenReturnsEmptyString(): void
    {
        // Arrange
        $property = ['type' => 'string'];
        $propertyName = 'email';
        $includes = [
            ['relationshipName' => 'email', 'targetResource' => 'CustomersEmails'],
        ];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('', $result);
    }

    public function testGivenBackendApiTypeWhenGeneratingThenUsesBackendInFqcn(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'items';
        $includes = [
            ['relationshipName' => 'items', 'targetResource' => 'OrdersItems'],
        ];
        $apiType = 'Backend';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('@var \Generated\Api\Backend\OrdersItemsBackendResource[]', $result);
    }

    public function testGivenResourceNameWithHyphensWhenGeneratingThenNormalizesToPascalCase(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'customerAddresses';
        $includes = [
            ['relationshipName' => 'customerAddresses', 'targetResource' => 'customers-addresses'],
        ];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('@var \Generated\Api\Storefront\CustomersAddressesStorefrontResource[]', $result);
    }

    public function testGivenEmptyIncludesArrayWhenGeneratingThenReturnsEmptyString(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'addresses';
        $includes = [];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('', $result);
    }

    public function testGivenPropertyWithoutTypeWhenGeneratingThenReturnsEmptyString(): void
    {
        // Arrange
        $property = [];
        $propertyName = 'addresses';
        $includes = [
            ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
        ];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('', $result);
    }

    public function testGivenLowercaseApiTypeWhenGeneratingThenNormalizesToUcfirst(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'addresses';
        $includes = [
            ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
        ];
        $apiType = 'storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('@var \Generated\Api\Storefront\CustomersAddressesStorefrontResource[]', $result);
    }

    public function testGivenMultipleIncludesWhenGeneratingThenUsesFirstMatch(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'addresses';
        $includes = [
            ['relationshipName' => 'orders', 'targetResource' => 'CustomersOrders'],
            ['relationshipName' => 'addresses', 'targetResource' => 'CustomersAddresses'],
            ['relationshipName' => 'addresses', 'targetResource' => 'OtherAddresses'],
        ];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('@var \Generated\Api\Storefront\CustomersAddressesStorefrontResource[]', $result);
    }

    public function testGivenKebabCaseRelationshipNameWhenGeneratingThenMatchesCamelCaseProperty(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'cartRules';
        $includes = [
            ['relationshipName' => 'cart-rules', 'targetResource' => 'CartRules'],
        ];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('@var \Generated\Api\Storefront\CartRulesStorefrontResource[]', $result);
    }

    public function testGivenComplexResourceNameWhenGeneratingThenNormalizesCorrectly(): void
    {
        // Arrange
        $property = ['type' => 'array'];
        $propertyName = 'oauth2Tokens';
        $includes = [
            ['relationshipName' => 'oauth2Tokens', 'targetResource' => 'OAuth2Tokens'],
        ];
        $apiType = 'Storefront';

        // Act
        $result = $this->generator->generate($property, $propertyName, $includes, $apiType);

        // Assert
        $this->assertSame('@var \Generated\Api\Storefront\OAuth2TokensStorefrontResource[]', $result);
    }
}
