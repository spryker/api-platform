<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Object\Finder;

use Codeception\Test\Unit;
use SplFileInfo;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Schema\Object\Finder\ObjectSchemaFinder;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Object
 * @group Finder
 * @group ObjectSchemaFinderTest
 * Add your own group annotations below this line
 */
class ObjectSchemaFinderTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenObjectAndValidationFilesWhenFindingObjectSchemasThenReturnsObjectFilesExcludingValidation(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'TestModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'objects' => [
                                'address.object.yml' => 'content',
                                'address.object.validation.yml' => 'content',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $finder = $this->createObjectSchemaFinder();

        // Act
        $files = iterator_to_array($finder->findObjectSchemas('storefront'));
        $names = array_map(static fn (SplFileInfo $f): string => $f->getFilename(), $files);

        // Assert
        $this->assertContains('address.object.yml', $names);
        $this->assertNotContains('address.object.validation.yml', $names);
    }

    public function testGivenObjectAndValidationFilesWhenFindingValidationSchemasThenReturnsOnlyValidationFiles(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'TestModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'objects' => [
                                'address.object.yml' => 'content',
                                'address.object.validation.yml' => 'content',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $finder = $this->createObjectSchemaFinder();

        // Act
        $files = iterator_to_array($finder->findObjectValidationSchemas('storefront'));
        $names = array_map(static fn (SplFileInfo $f): string => $f->getFilename(), $files);

        // Assert
        $this->assertContains('address.object.validation.yml', $names);
        $this->assertNotContains('address.object.yml', $names);
    }

    public function testGivenYamlExtensionObjectFileWhenFindingObjectSchemasThenReturnsTheFile(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'TestModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'objects' => [
                                'address.object.yaml' => 'content',
                                'address.object.validation.yaml' => 'content',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $finder = $this->createObjectSchemaFinder();

        // Act
        $files = iterator_to_array($finder->findObjectSchemas('storefront'));
        $names = array_map(static fn (SplFileInfo $f): string => $f->getFilename(), $files);

        // Assert
        $this->assertContains('address.object.yaml', $names);
        $this->assertNotContains('address.object.validation.yaml', $names);
    }

    public function testGivenUppercaseApiTypeWhenFindingObjectSchemasThenNormalizesAndReturnsFiles(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'TestModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'objects' => [
                                'address.object.yml' => 'content',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $finder = $this->createObjectSchemaFinder();

        // Act
        $files = iterator_to_array($finder->findObjectSchemas('Storefront'));

        // Assert
        $this->assertCount(1, $files);
    }

    public function testGivenNoObjectsDirectoryWhenFindingObjectSchemasThenReturnsEmpty(): void
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
        $finder = $this->createObjectSchemaFinder();

        // Act
        $files = iterator_to_array($finder->findObjectSchemas('storefront'));

        // Assert
        $this->assertEmpty($files);
    }

    public function testGivenConfiguredCentralDirectoryWhenFindingCentralObjectSchemasThenReturnsObjectFilesExcludingValidation(): void
    {
        // Arrange
        $centralDir = $this->createCentralDirectory('storefront', [
            'address.object.yml' => 'content',
            'address.object.validation.yml' => 'content',
        ]);
        $finder = $this->createObjectSchemaFinderWithCentralDirectories(['storefront' => [$centralDir]]);

        // Act
        $files = iterator_to_array($finder->findCentralObjectSchemas('storefront'));
        $names = array_map(static fn (SplFileInfo $f): string => $f->getFilename(), $files);

        // Assert
        $this->assertContains('address.object.yml', $names);
        $this->assertNotContains('address.object.validation.yml', $names);
    }

    public function testGivenConfiguredCentralDirectoryWhenFindingCentralValidationSchemasThenReturnsOnlyValidationFiles(): void
    {
        // Arrange
        $centralDir = $this->createCentralDirectory('storefront', [
            'address.object.yml' => 'content',
            'address.object.validation.yml' => 'content',
        ]);
        $finder = $this->createObjectSchemaFinderWithCentralDirectories(['storefront' => [$centralDir]]);

        // Act
        $files = iterator_to_array($finder->findCentralObjectValidationSchemas('storefront'));
        $names = array_map(static fn (SplFileInfo $f): string => $f->getFilename(), $files);

        // Assert
        $this->assertContains('address.object.validation.yml', $names);
        $this->assertNotContains('address.object.yml', $names);
    }

    public function testGivenEmptyCentralConfigWhenFindingCentralObjectSchemasThenReturnsNothing(): void
    {
        // Arrange
        $finder = $this->createObjectSchemaFinderWithCentralDirectories(['storefront' => []]);

        // Act
        $objectFiles = iterator_to_array($finder->findCentralObjectSchemas('storefront'));
        $validationFiles = iterator_to_array($finder->findCentralObjectValidationSchemas('storefront'));

        // Assert
        $this->assertSame([], $objectFiles);
        $this->assertSame([], $validationFiles);
    }

    public function testGivenNonExistentCentralDirectoryWhenFindingCentralObjectSchemasThenSkipsItAndReturnsNothing(): void
    {
        // Arrange
        $missingDir = sys_get_temp_dir() . '/central-missing-' . uniqid();
        $finder = $this->createObjectSchemaFinderWithCentralDirectories(['storefront' => [$missingDir]]);

        // Act
        $files = iterator_to_array($finder->findCentralObjectSchemas('storefront'));

        // Assert
        $this->assertSame([], $files);
    }

    /**
     * Creates a temporary central directory holding the given files and returns its absolute path.
     *
     * @param array<string, string> $files
     */
    protected function createCentralDirectory(string $apiType, array $files): string
    {
        $dir = sprintf('%s/central-%s-%s', sys_get_temp_dir(), $apiType, uniqid('', true));
        mkdir($dir, 0777, true);

        foreach ($files as $name => $content) {
            file_put_contents(sprintf('%s/%s', $dir, $name), $content);
        }

        return $dir;
    }

    protected function createObjectSchemaFinder(): ObjectSchemaFinder
    {
        $config = new ApiPlatformConfig(
            sourceDirectories: [$this->tester->getVirtualFilesystemPath()],
            cacheDir: sys_get_temp_dir(),
            generatedDir: sys_get_temp_dir(),
            apiTypes: ['Storefront', 'Backend'],
            debug: false,
            excludedPathFragments: [],
        );

        return new ObjectSchemaFinder($config);
    }

    /**
     * @param array<string, array<string>> $centralDirectoriesByApiType
     */
    protected function createObjectSchemaFinderWithCentralDirectories(array $centralDirectoriesByApiType): ObjectSchemaFinder
    {
        $config = new ApiPlatformConfig(
            sourceDirectories: [$this->tester->getVirtualFilesystemPath()],
            cacheDir: sys_get_temp_dir(),
            generatedDir: sys_get_temp_dir(),
            apiTypes: ['Storefront', 'Backend'],
            debug: false,
            excludedPathFragments: [],
            canonicalObjectSearchDirectories: $centralDirectoriesByApiType,
        );

        return new ObjectSchemaFinder($config);
    }
}
