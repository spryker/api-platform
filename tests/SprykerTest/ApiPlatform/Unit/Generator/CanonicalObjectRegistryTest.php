<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Generator\CanonicalObjectRegistry;
use Spryker\ApiPlatform\Generator\ConstraintFormatter;
use Spryker\ApiPlatform\Generator\FqcnConstraintResolver;
use Spryker\ApiPlatform\Generator\NestedObjectClassGenerator;
use Spryker\ApiPlatform\Generator\PropertyAttributeGenerator;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use Spryker\ApiPlatform\Generator\ValidationAttributeGenerator;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapper;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group CanonicalObjectRegistryTest
 * Add your own group annotations below this line
 */
class CanonicalObjectRegistryTest extends Unit
{
    public function testGivenResolvedDefinitionWhenBuildingThenGeneratesOneClassAndMarksItKnown(): void
    {
        // Arrange
        $registry = $this->createRegistry();

        // Act — a single resolved `Address` definition (output of CanonicalObjectDefinitionResolver).
        $result = $registry->build(
            ['Address' => ['zipCode' => ['name' => 'zipCode', 'type' => 'string']]],
            [],
            [],
            'Storefront',
        );

        // Assert — Address is known and exactly one class lands in the canonical namespace.
        $this->assertArrayHasKey('Address', $result->getKnownCanonicalObjectNames());

        $classes = $result->getCanonicalObjectClasses();
        $this->assertNotEmpty($classes);
        $this->assertStringContainsString('namespace Generated\Api\Storefront;', reset($classes));
        $this->assertStringContainsString('public ?string $zipCode = null;', reset($classes));
    }

    public function testGivenNoDefinitionsWhenBuildingThenGeneratesNothing(): void
    {
        // Arrange
        $registry = $this->createRegistry();

        // Act
        $result = $registry->build([], [], [], 'Storefront');

        // Assert
        $this->assertSame([], $result->getCanonicalObjectClasses());
        $this->assertSame([], $result->getKnownCanonicalObjectNames());
    }

    public function testGivenObjectNameCollidingWithResourceClassWhenBuildingThenThrows(): void
    {
        // Arrange — the objectName resolves to the same class name as a generated resource class.
        $registry = $this->createRegistry();
        $validatedSchemas = [
            'storefront_orders' => [
                'name' => 'Orders',
                'sourceFile' => 'orders.resource.yml',
            ],
        ];

        // Expect
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessageMatches('/collides with the generated resource class/');

        // Act
        $registry->build(
            ['OrdersStorefrontResource' => ['id' => ['name' => 'id', 'type' => 'integer']]],
            [],
            $validatedSchemas,
            'Storefront',
        );
    }

    public function testGivenObjectValidationWhenBuildingThenLiftsItOntoTheCanonicalClass(): void
    {
        // Arrange — a resolved `Address` plus an object-validation entry declaring NotBlank on zipCode.
        // The constraint must be re-emitted as a `#[Assert\NotBlank]` attribute on the value object.
        $registry = $this->createRegistry();
        $objectValidationSchemas = [
            'Address' => [
                'post' => [
                    'zipCode' => ['NotBlank'],
                ],
            ],
        ];

        // Act
        $result = $registry->build(
            ['Address' => ['zipCode' => ['name' => 'zipCode', 'type' => 'string']]],
            $objectValidationSchemas,
            [],
            'Storefront',
        );

        // Assert
        $classes = $result->getCanonicalObjectClasses();
        $address = reset($classes);
        $this->assertStringContainsString('use Symfony\Component\Validator\Constraints as Assert;', $address);
        $this->assertStringContainsString("#[Assert\\NotBlank(groups: ['address:create'])]", $address);
        $this->assertStringContainsString('public ?string $zipCode = null;', $address);
    }

    public function testGivenObjectValidationForMultipleHttpMethodsWhenBuildingThenLiftsAllOntoTheCanonicalClass(): void
    {
        // Arrange — a field validated on both post and patch must carry both groups.
        $registry = $this->createRegistry();
        $objectValidationSchemas = [
            'Address' => [
                'post' => ['zipCode' => ['NotBlank']],
                'patch' => ['zipCode' => ['NotBlank']],
            ],
        ];

        // Act
        $result = $registry->build(
            ['Address' => ['zipCode' => ['name' => 'zipCode', 'type' => 'string']]],
            $objectValidationSchemas,
            [],
            'Storefront',
        );

        // Assert — the identical constraint collapses to one attribute carrying both groups.
        $classes = $result->getCanonicalObjectClasses();
        $address = reset($classes);
        $this->assertStringContainsString("#[Assert\\NotBlank(groups: ['address:create', 'address:update'])]", $address);
        $this->assertSame(1, substr_count($address, 'Assert\\NotBlank'));
    }

    protected function createRegistry(): CanonicalObjectRegistry
    {
        return new CanonicalObjectRegistry(
            new NestedObjectClassGenerator(new PropertyAttributeGenerator(), new PhpTemplateRenderer()),
            new ValidationAttributeGenerator(
                new ValidationGroupMapper(),
                new ConstraintFormatter(),
                new FqcnConstraintResolver(),
            ),
        );
    }
}
