<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Merger;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Merger\SchemaMerger;
use Spryker\ApiPlatform\Schema\Validation\Merger\ValidationSchemaMergerInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Merger
 * @group SchemaMergerTest
 * Add your own group annotations below this line
 */
class SchemaMergerTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenSingleSchemaWhenMergingThenReturnsSameSchema(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'sourceLayer' => 'core'];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$schema], 'Customer', 'Storefront');

        // Assert
        $this->assertEquals('Customer', $result['name']);
    }

    public function testGivenEmptySchemasWhenMergingThenReturnsEmpty(): void
    {
        // Arrange
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([], 'Customer', 'Storefront');

        // Assert
        $this->assertEmpty($result);
    }

    public function testGivenCoreAndProjectWhenMergingThenProjectOverridesCore(): void
    {
        // Arrange
        $core = ['name' => 'Customer', 'description' => 'Core', 'sourceLayer' => 'core'];
        $project = ['description' => 'Project', 'sourceLayer' => 'project'];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$core, $project], 'Customer', 'Storefront');

        // Assert
        $this->assertEquals('Project', $result['description']);
    }

    public function testGivenPropertiesInMultipleLayersWhenMergingThenMergesProperties(): void
    {
        // Arrange
        $core = ['properties' => ['id' => ['type' => 'integer']], 'sourceLayer' => 'core'];
        $project = ['properties' => ['name' => ['type' => 'string']], 'sourceLayer' => 'project'];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$core, $project], 'Customer', 'Storefront');

        // Assert
        $this->assertArrayHasKey('id', $result['properties']);
        $this->assertArrayHasKey('name', $result['properties']);
    }

    public function testGivenPropertyOverrideWhenMergingThenOverridesProperty(): void
    {
        // Arrange
        $core = ['properties' => ['id' => ['type' => 'integer', 'writable' => true]], 'sourceLayer' => 'core'];
        $project = ['properties' => ['id' => ['writable' => false]], 'sourceLayer' => 'project'];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$core, $project], 'Customer', 'Storefront');

        // Assert
        $this->assertFalse($result['properties']['id']['writable']);
    }

    public function testGivenOperationsInMultipleLayersWhenMergingThenMergesOperations(): void
    {
        // Arrange
        $core = ['operations' => ['Get' => []], 'sourceLayer' => 'core'];
        $project = ['operations' => ['Post' => []], 'sourceLayer' => 'project'];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$core, $project], 'Customer', 'Storefront');

        // Assert
        $this->assertArrayHasKey('Get', $result['operations']);
        $this->assertArrayHasKey('Post', $result['operations']);
    }

    public function testGivenThreeLayersWhenMergingThenAppliesCorrectPriority(): void
    {
        // Arrange
        $core = ['description' => 'Core', 'sourceLayer' => 'core'];
        $feature = ['description' => 'Feature', 'sourceLayer' => 'feature'];
        $project = ['description' => 'Project', 'sourceLayer' => 'project'];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$core, $feature, $project], 'Customer', 'Storefront');

        // Assert
        $this->assertEquals('Project', $result['description']);
    }

    public function testGivenMergedSchemaWhenCheckingMetadataThenContainsContributingSources(): void
    {
        // Arrange
        $core = ['name' => 'Customer', 'sourceLayer' => 'core', 'sourceFile' => 'core.yaml'];
        $project = ['description' => 'Project', 'sourceLayer' => 'project', 'sourceFile' => 'project.yaml'];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$core, $project], 'Customer', 'Storefront');

        // Assert
        $this->assertArrayHasKey('_metadata', $result);
        $this->assertCount(2, $result['_metadata']['contributingSources']);
    }

    public function testGivenValidationSourceFilesWhenMergingThenMergesValidationSourceFiles(): void
    {
        // Arrange
        $core = [
            'validation' => ['post' => ['name' => ['NotBlank']]],
            'validationSourceFiles' => ['/path/to/core/validation.yaml'],
            'sourceLayer' => 'core',
        ];
        $project = [
            'validation' => ['put' => ['email' => ['Email']]],
            'validationSourceFiles' => ['/path/to/project/validation.yaml'],
            'sourceLayer' => 'project',
        ];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$core, $project], 'Customer', 'Storefront');

        // Assert
        $this->assertArrayHasKey('validationSourceFiles', $result);
        $this->assertCount(2, $result['validationSourceFiles']);
        $this->assertContains('/path/to/core/validation.yaml', $result['validationSourceFiles']);
        $this->assertContains('/path/to/project/validation.yaml', $result['validationSourceFiles']);
    }

    public function testGivenMultipleSchemasFromSameLayerWhenMergingThenMergesThem(): void
    {
        // Arrange
        $feature1 = [
            'name' => 'Customer',
            'operations' => ['Get' => [], 'Post' => []],
            'properties' => ['id' => ['type' => 'integer']],
            'sourceLayer' => 'feature',
            'sourceFile' => 'feature1.yaml',
        ];
        $feature2 = [
            'operations' => ['Put' => [], 'Patch' => []],
            'properties' => ['name' => ['type' => 'string']],
            'sourceLayer' => 'feature',
            'sourceFile' => 'feature2.yaml',
        ];
        $merger = $this->createSchemaMerger();

        // Act
        $result = $merger->merge([$feature1, $feature2], 'Customer', 'Storefront');

        // Assert
        $this->assertArrayHasKey('Get', $result['operations']);
        $this->assertArrayHasKey('Post', $result['operations']);
        $this->assertArrayHasKey('Put', $result['operations']);
        $this->assertArrayHasKey('Patch', $result['operations']);
        $this->assertArrayHasKey('id', $result['properties']);
        $this->assertArrayHasKey('name', $result['properties']);
    }

    protected function createSchemaMerger(): SchemaMerger
    {
        $validationSchemaMerger = $this->makeEmpty(ValidationSchemaMergerInterface::class, [
            'merge' => [],
        ]);

        return new SchemaMerger($validationSchemaMerger);
    }
}
