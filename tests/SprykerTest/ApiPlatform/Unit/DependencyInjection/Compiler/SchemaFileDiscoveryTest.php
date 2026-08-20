<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection\Compiler;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\DependencyInjection\Compiler\SchemaFileDiscovery;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Guards the discovery contract shared by the compiler passes: schema files are found through the
 * configured source directories and the result is memoized per (source directories, API type) pair,
 * because several passes request the same files during a single container build.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group Compiler
 * @group SchemaFileDiscoveryTest
 * Add your own group annotations below this line
 */
class SchemaFileDiscoveryTest extends Unit
{
    protected const string API_TYPE = 'storefront';

    protected ApiUnitTester $tester;

    protected string $tmpDir = '';

    protected function _before(): void
    {
        $this->tmpDir = sprintf('%s/schema-file-discovery-%s', sys_get_temp_dir(), uniqid());
        mkdir(sprintf('%s/FakeModule/resources/api/%s', $this->tmpDir, static::API_TYPE), 0777, true);
    }

    protected function _after(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testGivenSchemaFilesInSourceDirectoriesWhenFindingThenTheyAreReturnedSorted(): void
    {
        // Arrange
        $this->createSchemaFile('FakeModule', 'beta.resource.yml');
        $this->createSchemaFile('FakeModule', 'alpha.resource.yaml');

        $schemaFileDiscovery = new SchemaFileDiscovery();

        // Act
        $schemaFiles = $schemaFileDiscovery->findSchemaFiles([$this->tmpDir], static::API_TYPE);

        // Assert
        $this->assertSame(
            ['alpha.resource.yaml', 'beta.resource.yml'],
            array_map(static fn ($schemaFile) => $schemaFile->getFilename(), $schemaFiles),
        );
    }

    public function testGivenRepeatedLookupWhenFindingThenResultIsMemoized(): void
    {
        // Arrange
        $this->createSchemaFile('FakeModule', 'first.resource.yml');

        $schemaFileDiscovery = new SchemaFileDiscovery();
        $firstResult = $schemaFileDiscovery->findSchemaFiles([$this->tmpDir], static::API_TYPE);

        // A file created after the first lookup must not appear again for the same instance.
        $this->createSchemaFile('FakeModule', 'late.resource.yml');

        // Act
        $secondResult = $schemaFileDiscovery->findSchemaFiles([$this->tmpDir], static::API_TYPE);

        // Assert
        $this->assertCount(1, $firstResult);
        $this->assertSame($firstResult, $secondResult);
    }

    public function testGivenDifferentApiTypeWhenFindingThenCacheEntriesDoNotCollide(): void
    {
        // Arrange
        $this->createSchemaFile('FakeModule', 'storefront-only.resource.yml');
        mkdir(sprintf('%s/FakeModule/resources/api/backend', $this->tmpDir), 0777, true);

        $schemaFileDiscovery = new SchemaFileDiscovery();

        // Act
        $storefrontResult = $schemaFileDiscovery->findSchemaFiles([$this->tmpDir], static::API_TYPE);
        $backendResult = $schemaFileDiscovery->findSchemaFiles([$this->tmpDir], 'backend');

        // Assert
        $this->assertCount(1, $storefrontResult);
        $this->assertSame([], $backendResult);
    }

    public function testGivenNoMatchingDirectoriesWhenFindingThenEmptyResultIsMemoized(): void
    {
        // Arrange
        $schemaFileDiscovery = new SchemaFileDiscovery();
        $missingSourceDirectory = sprintf('%s/does-not-exist', $this->tmpDir);

        // Act
        $firstResult = $schemaFileDiscovery->findSchemaFiles([$missingSourceDirectory], static::API_TYPE);
        $secondResult = $schemaFileDiscovery->findSchemaFiles([$missingSourceDirectory], static::API_TYPE);

        // Assert
        $this->assertSame([], $firstResult);
        $this->assertSame([], $secondResult);
    }

    protected function createSchemaFile(string $moduleName, string $fileName): void
    {
        file_put_contents(
            sprintf('%s/%s/resources/api/%s/%s', $this->tmpDir, $moduleName, static::API_TYPE, $fileName),
            "resource:\n    name: Fake\n",
        );
    }

    protected function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = sprintf('%s/%s', $directory, $item);

            if (is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
