<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Validation\Finder;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Schema\Validation\Finder\ValidationSchemaFinder;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Validation
 * @group Finder
 * @group ValidationSchemaFinderTest
 * Add your own group annotations below this line
 */
class ValidationSchemaFinderTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenValidationSchemasInModuleDirectoriesWhenFindingAllThenTheyAreReturned(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'ModuleA' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'customers.validation.yml' => 'constraints: []',
                        ],
                    ],
                ],
            ],
            'ModuleB' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'orders.validation.yaml' => 'constraints: []',
                        ],
                    ],
                ],
            ],
        ]);
        $validationSchemaFinder = $this->createValidationSchemaFinder();

        // Act
        $files = iterator_to_array($validationSchemaFinder->findAllValidationSchemas('Storefront'));

        // Assert
        $this->assertSame(
            ['customers.validation.yml', 'orders.validation.yaml'],
            array_map(static fn ($file) => $file->getFilename(), array_values($files)),
        );
    }

    public function testGivenNoMatchingApiTypeDirectoriesWhenFindingAllThenReturnsEmpty(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'ModuleA' => [
                'resources' => [
                    'api' => [
                        'backend' => [
                            'customers.validation.yml' => 'constraints: []',
                        ],
                    ],
                ],
            ],
        ]);
        $validationSchemaFinder = $this->createValidationSchemaFinder();

        // Act
        $files = iterator_to_array($validationSchemaFinder->findAllValidationSchemas('Storefront'));

        // Assert
        $this->assertSame([], $files);
    }

    public function testGivenExcludedPathFragmentWhenFindingAllThenMatchingFilesAreSkipped(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'ExcludedModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'excluded.validation.yml' => 'constraints: []',
                        ],
                    ],
                ],
            ],
            'KeptModule' => [
                'resources' => [
                    'api' => [
                        'storefront' => [
                            'kept.validation.yml' => 'constraints: []',
                        ],
                    ],
                ],
            ],
        ]);
        $validationSchemaFinder = $this->createValidationSchemaFinder(['ExcludedModule/resources/api/']);

        // Act
        $files = iterator_to_array($validationSchemaFinder->findAllValidationSchemas('Storefront'));

        // Assert
        $this->assertSame(
            ['kept.validation.yml'],
            array_map(static fn ($file) => $file->getFilename(), array_values($files)),
        );
    }

    public function testGivenApiTypeWhenGettingDiagnosticInfoThenExistingAndSkippedDirectoriesAreReported(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'ModuleA' => [
                'resources' => [
                    'api' => [
                        'storefront' => [],
                    ],
                ],
            ],
        ]);
        $validationSchemaFinder = $this->createValidationSchemaFinder(
            [],
            [sprintf('%s/does-not-exist', sys_get_temp_dir())],
        );

        // Act
        $diagnosticInfo = $validationSchemaFinder->getValidationDiagnosticInfo('Storefront');

        // Assert
        $this->assertSame(1, $diagnosticInfo['directories_found_count']);
        $this->assertStringEndsWith('ModuleA/resources/api/storefront', $diagnosticInfo['existing_directories'][0]);
        $this->assertSame([sprintf('%s/does-not-exist', sys_get_temp_dir())], $diagnosticInfo['skipped_directories']);
    }

    /**
     * @param array<string> $excludedPathFragments
     * @param array<string> $additionalSourceDirectories
     */
    protected function createValidationSchemaFinder(
        array $excludedPathFragments = [],
        array $additionalSourceDirectories = [],
    ): ValidationSchemaFinder {
        $config = new ApiPlatformConfig(
            sourceDirectories: array_merge([$this->tester->getVirtualFilesystemPath()], $additionalSourceDirectories),
            cacheDir: sys_get_temp_dir(),
            generatedDir: sys_get_temp_dir(),
            apiTypes: ['Storefront', 'Backend'],
            debug: false,
            excludedPathFragments: $excludedPathFragments,
        );

        return new ValidationSchemaFinder($config);
    }
}
