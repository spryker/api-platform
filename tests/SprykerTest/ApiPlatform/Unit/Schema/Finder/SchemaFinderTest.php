<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Finder;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Schema\Finder\SchemaFinder;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Finder
 * @group SchemaFinderTest
 * Add your own group annotations below this line
 */
class SchemaFinderTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenValidApiTypeWhenFindingSchemaFilesThenReturnsFiles(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'TestModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'Customer.resource.yaml' => 'content',
                            'Order.resource.yaml' => 'content',
                        ],
                    ],
                ],
            ],
        ]);
        $schemaFinder = $this->createSchemaFinder();

        // Act
        $files = iterator_to_array($schemaFinder->findSchemaFiles('Storefront'));

        // Assert
        $this->assertCount(2, $files);
    }

    public function testGivenEmptyDirectoryWhenFindingSchemaFilesThenReturnsEmpty(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'TestModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [],
                    ],
                ],
            ],
        ]);
        $schemaFinder = $this->createSchemaFinder();

        // Act
        $files = iterator_to_array($schemaFinder->findSchemaFiles('Storefront'));

        // Assert
        $this->assertEmpty($files);
    }

    public function testGivenNonExistentApiTypeWhenFindingSchemaFilesThenReturnsEmpty(): void
    {
        // Arrange
        $schemaFinder = $this->createSchemaFinder();

        // Act
        $files = iterator_to_array($schemaFinder->findSchemaFiles('NonExistent'));

        // Assert
        $this->assertEmpty($files);
    }

    public function testGivenMultipleExtensionsWhenFindingSchemaFilesThenReturnsAllExtensions(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'TestModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'Customer.resource.yaml' => 'content',
                            'Order.resource.yml' => 'content',
                        ],
                    ],
                ],
            ],
        ]);
        $schemaFinder = $this->createSchemaFinder();

        // Act
        $files = iterator_to_array($schemaFinder->findSchemaFiles('Storefront'));

        // Assert
        $this->assertCount(2, $files);
    }

    public function testGivenFilesInMultipleModulesWhenFindingSchemaFilesThenReturnsAllFiles(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'CustomerModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'Customer.resource.yaml' => 'content',
                            'CustomerAddress.resource.yaml' => 'content',
                        ],
                    ],
                ],
            ],
            'OrderModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'Order.resource.yaml' => 'content',
                            'OrderItem.resource.yaml' => 'content',
                        ],
                    ],
                ],
            ],
        ]);
        $schemaFinder = $this->createSchemaFinder();

        // Act
        $files = iterator_to_array($schemaFinder->findSchemaFiles('Storefront'));

        // Assert
        $this->assertCount(4, $files);
    }

    public function createSchemaFinder(): SchemaFinder
    {
        $config = new ApiPlatformConfig(
            sourceDirectories: [$this->tester->getVirtualFilesystemPath()],
            cacheDir: sys_get_temp_dir(),
            generatedDir: sys_get_temp_dir(),
            apiTypes: ['Storefront', 'Backend'],
            debug: false,
        );

        return new SchemaFinder($config);
    }
}
