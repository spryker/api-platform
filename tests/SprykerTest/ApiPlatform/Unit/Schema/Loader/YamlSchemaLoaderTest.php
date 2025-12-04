<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Loader;

use Codeception\Test\Unit;
use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Schema\Loader\YamlSchemaLoader;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Loader
 * @group YamlSchemaLoaderTest
 * Add your own group annotations below this line
 */
class YamlSchemaLoaderTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenValidYamlFileWhenLoadingThenReturnsArray(): void
    {
        // Arrange
        $filePath = $this->createYamlFile('resource: { name: Customer }');
        $loader = new YamlSchemaLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath));

        // Assert
        $this->assertIsArray($result);
    }

    public function testGivenYamlFileWithoutResourceKeyWhenLoadingThenThrowsException(): void
    {
        // Arrange
        $filePath = $this->createYamlFile('invalid: data');
        $loader = new YamlSchemaLoader();

        // Expect
        $this->expectException(ApiSchemaValidationException::class);
        $this->expectExceptionMessage('Schema file must have a "resource" root key');

        // Act
        $loader->load(new SplFileInfo($filePath));
    }

    public function testGivenInvalidYamlSyntaxWhenLoadingThenThrowsException(): void
    {
        // Arrange
        $filePath = $this->createYamlFile('invalid: [yaml syntax');
        $loader = new YamlSchemaLoader();

        // Expect
        $this->expectException(ApiSchemaValidationException::class);
        $this->expectExceptionMessage('YAML syntax error');

        // Act
        $loader->load(new SplFileInfo($filePath));
    }

    public function testGivenYamlExtensionWhenCheckingSupportThenReturnsTrue(): void
    {
        // Arrange
        $filePath = $this->createYamlFile('resource: {}');
        $loader = new YamlSchemaLoader();

        // Act
        $supports = $loader->supports(new SplFileInfo($filePath));

        // Assert
        $this->assertTrue($supports);
    }

    public function testGivenYmlExtensionWhenCheckingSupportThenReturnsTrue(): void
    {
        // Arrange
        $filePath = $this->createYmlFile('resource: {}');
        $loader = new YamlSchemaLoader();

        // Act
        $supports = $loader->supports(new SplFileInfo($filePath));

        // Assert
        $this->assertTrue($supports);
    }

    protected function createYamlFile(string $content): string
    {
        $path = sprintf('%s/test-%s.yaml', sys_get_temp_dir(), uniqid());
        file_put_contents($path, $content);

        return $path;
    }

    protected function createYmlFile(string $content): string
    {
        $path = sprintf('%s/test-%s.yml', sys_get_temp_dir(), uniqid());
        file_put_contents($path, $content);

        return $path;
    }
}
