<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\PropertyAttributeGenerator;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group PropertyAttributeGeneratorTest
 * Add your own group annotations below this line
 */
class PropertyAttributeGeneratorTest extends Unit
{
    protected const string PROPERTY_NAME = 'sendRegistrationToken';

    protected const string RESOURCE_NAME = 'customers';

    protected ApiUnitTester $tester;

    public function testGivenAResponseOptionalPropertyWhenGeneratingThenTheApiPropertyCarriesTheExtraProperty(): void
    {
        // Arrange
        $property = ['type' => 'string', 'writable' => false, 'readable' => true, 'responseOptional' => true];

        // Act
        $attributes = (new PropertyAttributeGenerator())->generate($property, [], [], 'updatedAt', 'wishlists');

        // Assert
        $this->assertStringContainsString("extraProperties: ['responseOptional' => true]", $attributes);
    }

    public function testGivenGroupsWhenGeneratingThenEmitsTheGroupsAttribute(): void
    {
        // Arrange
        $property = ['type' => 'boolean', 'groups' => ['customers:write', 'customers:create']];
        $generator = new PropertyAttributeGenerator();

        // Act
        $result = $generator->generate($property, [], [], static::PROPERTY_NAME, static::RESOURCE_NAME);

        // Assert
        $this->assertStringContainsString("#[Groups(['customers:write', 'customers:create'])]", $result);
    }

    public function testGivenNoGroupsWhenGeneratingThenEmitsNoGroupsAttribute(): void
    {
        // Arrange
        $property = ['type' => 'boolean', 'openapiContext' => ['example' => true]];
        $generator = new PropertyAttributeGenerator();

        // Act
        $result = $generator->generate($property, [], [], static::PROPERTY_NAME, static::RESOURCE_NAME);

        // Assert
        $this->assertStringNotContainsString('#[Groups(', $result);
    }

    public function testGivenASyntheticIdentifierPropertyWhenGeneratingThenTheApiPropertyCarriesTheExtraProperty(): void
    {
        // Arrange
        $property = ['type' => 'string', 'identifier' => true, 'syntheticIdentifier' => true, 'writable' => false];

        // Act
        $attributes = (new PropertyAttributeGenerator())->generate($property, [], [], 'checkoutDataId', 'checkout-data');

        // Assert
        $this->assertStringContainsString("extraProperties: ['syntheticIdentifier' => true]", $attributes);
        $this->assertStringContainsString('identifier: true', $attributes);
    }

    public function testGivenBothExtraPropertyFlagsWhenGeneratingThenTheyShareOneExtraPropertiesArgument(): void
    {
        // Arrange
        $property = ['type' => 'string', 'identifier' => true, 'syntheticIdentifier' => true, 'responseOptional' => true];

        // Act
        $attributes = (new PropertyAttributeGenerator())->generate($property, [], [], 'checkoutDataId', 'checkout-data');

        // Assert
        $this->assertStringContainsString(
            "extraProperties: ['responseOptional' => true, 'syntheticIdentifier' => true]",
            $attributes,
        );
    }
}
