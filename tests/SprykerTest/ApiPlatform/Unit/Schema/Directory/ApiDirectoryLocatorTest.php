<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Directory;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Schema\Directory\ApiDirectoryLocator;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Guards the contract between the configured `spryker_api_platform.source_directories` and the directory
 * lookup: every configured source directory MUST be honored for all supported module layouts, and
 * unavailable directories MUST be skipped gracefully.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Directory
 * @group ApiDirectoryLocatorTest
 * Add your own group annotations below this line
 */
class ApiDirectoryLocatorTest extends Unit
{
    protected const string API_TYPE = 'storefront';

    protected ApiUnitTester $tester;

    protected string $tmpDir = '';

    protected function _before(): void
    {
        $this->tmpDir = sprintf('%s/api-directory-locator-%s', sys_get_temp_dir(), uniqid());
        mkdir($this->tmpDir, 0777, true);
    }

    protected function _after(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testGivenAllSupportedLayoutsWhenLocatingResourceSchemaDirectoriesThenEverySourceDirectoryIsHonored(): void
    {
        // Arrange
        $moduleRootDirectory = $this->createDirectory('module-root/resources/api/%s');
        $moduleParentDirectory = $this->createDirectory('src-spryker/FakeModule/resources/api/%s');
        $organizationDirectory = $this->createDirectory('vendor/spryker-eco/fake-package/resources/api/%s');
        // Documented project-level layout: src/Pyz/Glue/{Module}/resources/api/{apiType}
        // @see https://docs.spryker.com/docs/integrations/spryker-api/api-platform/enablement
        $projectDirectory = $this->createDirectory('pyz/Glue/FakeModule/resources/api/%s');

        $apiDirectoryLocator = new ApiDirectoryLocator();

        // Act
        $directories = $apiDirectoryLocator->locateResourceSchemaDirectories(
            [
                sprintf('%s/module-root', $this->tmpDir),
                sprintf('%s/src-spryker', $this->tmpDir),
                sprintf('%s/vendor', $this->tmpDir),
                sprintf('%s/pyz', $this->tmpDir),
            ],
            static::API_TYPE,
        );

        // Assert
        $this->assertEqualsCanonicalizing(
            [$moduleRootDirectory, $moduleParentDirectory, $organizationDirectory, $projectDirectory],
            $directories,
        );
    }

    public function testGivenMissingAndUnmatchedSourceDirectoriesWhenLocatingResourceSchemaDirectoriesThenTheyAreSkipped(): void
    {
        // Arrange
        $moduleParentDirectory = $this->createDirectory('src-spryker/FakeModule/resources/api/%s');
        $this->createDirectory('src-empty/FakeModule/resources/no-api-here/%s');

        $apiDirectoryLocator = new ApiDirectoryLocator();

        // Act
        $directories = $apiDirectoryLocator->locateResourceSchemaDirectories(
            [
                sprintf('%s/src-spryker', $this->tmpDir),
                sprintf('%s/src-empty', $this->tmpDir),
                sprintf('%s/does-not-exist', $this->tmpDir),
            ],
            static::API_TYPE,
        );

        // Assert
        $this->assertSame([$moduleParentDirectory], $directories);
    }

    public function testGivenApiTypeInMixedCaseWhenLocatingResourceSchemaDirectoriesThenLookupIsLowercased(): void
    {
        // Arrange
        $moduleParentDirectory = $this->createDirectory('src-spryker/FakeModule/resources/api/%s');

        $apiDirectoryLocator = new ApiDirectoryLocator();

        // Act
        $directories = $apiDirectoryLocator->locateResourceSchemaDirectories(
            [sprintf('%s/src-spryker', $this->tmpDir)],
            'Storefront',
        );

        // Assert
        $this->assertSame([$moduleParentDirectory], $directories);
    }

    public function testGivenAllSupportedLayoutsWhenLocatingApiClassDirectoriesThenEverySourceDirectoryIsHonored(): void
    {
        // Arrange
        $classicProjectDirectory = $this->createDirectory('pyz/Glue/FakeModule/Api/Storefront');
        $moduleCheckoutDirectory = $this->createDirectory('src-spryker/FakeModule/src/Spryker/Glue/FakeModule/Api/Storefront');
        $moduleSourceDirectory = $this->createDirectory('module-root/src/Spryker/Glue/FakeModule/Api/Storefront');

        $apiDirectoryLocator = new ApiDirectoryLocator();

        // Act
        $directories = $apiDirectoryLocator->locateApiClassDirectories(
            [
                sprintf('%s/pyz', $this->tmpDir),
                sprintf('%s/src-spryker', $this->tmpDir),
                sprintf('%s/module-root', $this->tmpDir),
            ],
            'Storefront',
        );

        // Assert
        $this->assertEqualsCanonicalizing(
            [$classicProjectDirectory, $moduleCheckoutDirectory, $moduleSourceDirectory],
            $directories,
        );
    }

    public function testGivenApiTypeInAnyCasingWhenLocatingApiClassDirectoriesThenLookupIsNormalized(): void
    {
        // Arrange
        $apiClassDirectory = $this->createDirectory('pyz/Glue/FakeModule/Api/Storefront');

        $apiDirectoryLocator = new ApiDirectoryLocator();

        // Act
        $lowercaseResult = $apiDirectoryLocator->locateApiClassDirectories([sprintf('%s/pyz', $this->tmpDir)], 'storefront');
        $uppercaseResult = $apiDirectoryLocator->locateApiClassDirectories([sprintf('%s/pyz', $this->tmpDir)], 'STOREFRONT');

        // Assert
        $this->assertSame([$apiClassDirectory], $lowercaseResult);
        $this->assertSame([$apiClassDirectory], $uppercaseResult);
    }

    public function testGivenSymlinkedModuleDirectoryWhenLocatingResourceSchemaDirectoriesThenItIsRejected(): void
    {
        // Arrange
        $moduleParentDirectory = $this->createDirectory('src-spryker/RealModule/resources/api/%s');
        $this->createDirectory('elsewhere/LinkedModule/resources/api/%s');
        symlink(sprintf('%s/elsewhere/LinkedModule', $this->tmpDir), sprintf('%s/src-spryker/LinkedModule', $this->tmpDir));

        $apiDirectoryLocator = new ApiDirectoryLocator();

        // Act
        $directories = $apiDirectoryLocator->locateResourceSchemaDirectories(
            [sprintf('%s/src-spryker', $this->tmpDir)],
            static::API_TYPE,
        );

        // Assert
        $this->assertSame([$moduleParentDirectory], $directories);
    }

    public function testGivenSourceDirectoryWithTrailingSlashWhenLocatingResourceSchemaDirectoriesThenItIsNormalized(): void
    {
        // Arrange
        $moduleParentDirectory = $this->createDirectory('src-spryker/FakeModule/resources/api/%s');

        $apiDirectoryLocator = new ApiDirectoryLocator();

        // Act
        $directories = $apiDirectoryLocator->locateResourceSchemaDirectories(
            [sprintf('%s/src-spryker/', $this->tmpDir)],
            static::API_TYPE,
        );

        // Assert
        $this->assertSame([$moduleParentDirectory], $directories);
    }

    public function testGivenRepeatedLookupWhenLocatingResourceSchemaDirectoriesThenResultIsMemoized(): void
    {
        // Arrange
        $moduleParentDirectory = $this->createDirectory('src-spryker/FakeModule/resources/api/%s');
        $sourceDirectories = [sprintf('%s/src-spryker', $this->tmpDir)];

        $apiDirectoryLocator = new ApiDirectoryLocator();
        $firstResult = $apiDirectoryLocator->locateResourceSchemaDirectories($sourceDirectories, static::API_TYPE);

        // A directory created after the first lookup must not appear again for the same instance.
        $this->createDirectory('src-spryker/LateModule/resources/api/%s');

        // Act
        $secondResult = $apiDirectoryLocator->locateResourceSchemaDirectories($sourceDirectories, static::API_TYPE);

        // Assert
        $this->assertSame([$moduleParentDirectory], $firstResult);
        $this->assertSame($firstResult, $secondResult);
    }

    protected function createDirectory(string $relativePathPattern): string
    {
        $directory = sprintf('%s/%s', $this->tmpDir, sprintf($relativePathPattern, static::API_TYPE));
        mkdir($directory, 0777, true);

        return (string)realpath($directory);
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

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
