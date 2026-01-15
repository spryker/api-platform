<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validation\Merger;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Validation\Merger\ValidationSchemaMerger;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validation
 * @group Merger
 * @group ValidationSchemaMergerTest
 * Add your own group annotations below this line
 */
class ValidationSchemaMergerTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenEmptySchemasWhenMergingThenReturnsEmpty(): void
    {
        // Arrange
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([]);

        // Assert
        $this->assertEmpty($result);
    }

    public function testGivenSingleSchemaWhenMergingThenReturnsSameSchema(): void
    {
        // Arrange
        $schema = [
            'post' => [
                'email' => ['NotBlank', 'Email'],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema]);

        // Assert
        $this->assertEquals($schema, $result);
    }

    public function testGivenTwoSchemasWithDifferentFieldsWhenMergingThenMergesFields(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'email' => ['NotBlank', 'Email'],
            ],
        ];
        $schema2 = [
            'post' => [
                'name' => ['NotBlank'],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertArrayHasKey('email', $result['post']);
        $this->assertArrayHasKey('name', $result['post']);
        $this->assertEquals(['NotBlank', 'Email'], $result['post']['email']);
        $this->assertEquals(['NotBlank'], $result['post']['name']);
    }

    public function testGivenDuplicateSimpleConstraintsWhenMergingThenDeduplicates(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'email' => ['NotBlank', 'Email'],
            ],
        ];
        $schema2 = [
            'post' => [
                'email' => ['NotBlank', 'Email'],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertCount(2, $result['post']['email']);
        $this->assertContains('NotBlank', $result['post']['email']);
        $this->assertContains('Email', $result['post']['email']);
    }

    public function testGivenDuplicateConstraintsWithParametersWhenMergingThenDeduplicates(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'name' => [
                    'NotBlank',
                    ['Length' => ['max' => 100]],
                ],
            ],
        ];
        $schema2 = [
            'post' => [
                'name' => [
                    'NotBlank',
                    ['Length' => ['max' => 100]],
                ],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertCount(2, $result['post']['name']);
        $this->assertContains('NotBlank', $result['post']['name']);
        $this->assertContains(['Length' => ['max' => 100]], $result['post']['name']);
    }

    public function testGivenPartialDuplicatesWhenMergingThenKeepsUniqueConstraints(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'password' => [
                    'NotBlank',
                    ['Length' => ['min' => 8, 'max' => 128]],
                ],
            ],
        ];
        $schema2 = [
            'post' => [
                'password' => [
                    'NotBlank',
                    ['Regex' => ['pattern' => '/^(?=.*[A-Z])/']],
                ],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertCount(3, $result['post']['password']);
        $this->assertContains('NotBlank', $result['post']['password']);
        $this->assertContains(['Length' => ['min' => 8, 'max' => 128]], $result['post']['password']);
        $this->assertContains(['Regex' => ['pattern' => '/^(?=.*[A-Z])/']], $result['post']['password']);
    }

    public function testGivenSameConstraintTypeDifferentParametersWhenMergingThenLastSchemaWins(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'description' => [
                    ['Length' => ['max' => 100]],
                ],
            ],
        ];
        $schema2 = [
            'post' => [
                'description' => [
                    ['Length' => ['max' => 500]],
                ],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertCount(1, $result['post']['description']);
        $this->assertEquals(['Length' => ['max' => 500]], $result['post']['description'][0]);
    }

    public function testGivenMultipleHttpMethodsWhenMergingThenDeduplicatesPerMethod(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'email' => ['NotBlank', 'Email'],
            ],
            'patch' => [
                'email' => ['Email'],
            ],
        ];
        $schema2 = [
            'post' => [
                'email' => ['NotBlank'],
            ],
            'patch' => [
                'email' => ['Email', 'NotBlank'],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertCount(2, $result['post']['email']);
        $this->assertContains('NotBlank', $result['post']['email']);
        $this->assertContains('Email', $result['post']['email']);
        $this->assertCount(2, $result['patch']['email']);
        $this->assertContains('Email', $result['patch']['email']);
        $this->assertContains('NotBlank', $result['patch']['email']);
    }

    public function testGivenThreeLayersWithDuplicatesWhenMergingThenProjectLayerWins(): void
    {
        // Arrange
        $core = [
            'post' => [
                'name' => ['NotBlank', ['Length' => ['max' => 100]]],
            ],
        ];
        $feature = [
            'post' => [
                'name' => ['NotBlank', ['Length' => ['max' => 200]], ['Regex' => ['pattern' => '/^[a-z]+$/']]],
            ],
        ];
        $project = [
            'post' => [
                'name' => ['NotBlank', ['Length' => ['max' => 500]], ['Regex' => ['pattern' => '/^[a-zA-Z]+$/']]],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$core, $feature, $project]);

        // Assert
        $this->assertCount(3, $result['post']['name']);
        $this->assertContains('NotBlank', $result['post']['name']);
        $this->assertEquals(['Length' => ['max' => 500]], $result['post']['name'][1]);
        $this->assertEquals(['Regex' => ['pattern' => '/^[a-zA-Z]+$/']], $result['post']['name'][2]);
    }

    public function testGivenComplexNestedConstraintsWhenMergingThenDeduplicatesCorrectly(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'address' => [
                    ['Optional' => ['constraints' => ['NotBlank', ['Length' => ['max' => 255]]]]],
                ],
            ],
        ];
        $schema2 = [
            'post' => [
                'address' => [
                    ['Optional' => ['constraints' => ['NotBlank', ['Length' => ['max' => 255]]]]],
                ],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertCount(1, $result['post']['address']);
        $this->assertEquals(
            ['Optional' => ['constraints' => ['NotBlank', ['Length' => ['max' => 255]]]]],
            $result['post']['address'][0],
        );
    }

    public function testGivenConstraintsWithParametersInDifferentOrderWhenMergingThenRecognizesAsDuplicate(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'name' => [
                    ['Length' => ['min' => 3, 'max' => 100]],
                ],
            ],
        ];
        $schema2 = [
            'post' => [
                'name' => [
                    ['Length' => ['max' => 100, 'min' => 3]],
                ],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertCount(1, $result['post']['name']);
        $this->assertEquals(['Length' => ['min' => 3, 'max' => 100]], $result['post']['name'][0]);
    }

    public function testGivenMixedConstraintFormatsWhenMergingThenNormalizesAndDeduplicates(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'email' => [
                    'NotBlank',
                    'Email',
                ],
            ],
        ];
        $schema2 = [
            'post' => [
                'email' => [
                    'NotBlank',
                    ['Email' => []],
                ],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2]);

        // Assert
        $this->assertCount(2, $result['post']['email']);
        $this->assertContains('NotBlank', $result['post']['email']);
        $this->assertTrue(
            in_array('Email', $result['post']['email'], true) || in_array(['Email' => []], $result['post']['email'], true),
        );
    }

    public function testGivenCoreMax100ProjectMax500WhenMergingThenProjectWins(): void
    {
        // Arrange
        $core = [
            'post' => [
                'description' => [
                    ['Length' => ['max' => 100]],
                ],
            ],
        ];
        $project = [
            'post' => [
                'description' => [
                    ['Length' => ['max' => 500]],
                ],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$core, $project]);

        // Assert
        $this->assertCount(1, $result['post']['description']);
        $this->assertEquals(['Length' => ['max' => 500]], $result['post']['description'][0]);
    }

    public function testGivenMultipleDifferentConstraintTypesWhenMergingThenAllAreKept(): void
    {
        // Arrange
        $schema1 = [
            'post' => [
                'email' => ['NotBlank'],
            ],
        ];
        $schema2 = [
            'post' => [
                'email' => ['Email'],
            ],
        ];
        $schema3 = [
            'post' => [
                'email' => [['Length' => ['max' => 255]]],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$schema1, $schema2, $schema3]);

        // Assert
        $this->assertCount(3, $result['post']['email']);
        $this->assertContains('NotBlank', $result['post']['email']);
        $this->assertContains('Email', $result['post']['email']);
        $this->assertContains(['Length' => ['max' => 255]], $result['post']['email']);
    }

    public function testGivenMiddleLayerDefinesConstraintWhenMergingThreeLayersThenProjectLayerWins(): void
    {
        // Arrange
        $core = [
            'post' => [
                'title' => ['NotBlank'],
            ],
        ];
        $feature = [
            'post' => [
                'title' => ['NotBlank', ['Length' => ['max' => 200]]],
            ],
        ];
        $project = [
            'post' => [
                'title' => ['NotBlank', ['Length' => ['max' => 500]]],
            ],
        ];
        $merger = new ValidationSchemaMerger();

        // Act
        $result = $merger->merge([$core, $feature, $project]);

        // Assert
        $this->assertCount(2, $result['post']['title']);
        $this->assertEquals('NotBlank', $result['post']['title'][0]);
        $this->assertEquals(['Length' => ['max' => 500]], $result['post']['title'][1]);
    }
}
