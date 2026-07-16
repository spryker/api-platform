<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Object\Loader;

use Codeception\Test\Unit;
use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Schema\Object\Loader\ObjectSchemaLoader;
use Spryker\ApiPlatform\Schema\Object\Loader\ObjectSchemaLoaderInterface;
use Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface;
use Spryker\ApiPlatform\Schema\Validation\Loader\ValidationSchemaLoader;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Object
 * @group Loader
 * @group ObjectSchemaLoaderTest
 * Add your own group annotations below this line
 */
class ObjectSchemaLoaderTest extends Unit
{
    protected ApiUnitTester $tester;

    /**
     * A core-path fixture (no /Pyz/ in path) with a type alias should be normalized and detected as core.
     *
     * @return void
     */
    public function testGivenCorePathObjectFileWhenLoadingThenNormalizesTypeAliasAndDetectsCoreLayer(): void
    {
        // Arrange
        $content = <<<YAML
object:
    name: Address
    properties:
        zipCode: { type: str, description: 'ZIP.' }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'address.object.yml', '/Spryker/SomeModule/');
        $loader = $this->createLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath));

        // Assert
        $this->assertSame('Address', $result['name']);
        $this->assertSame('string', $result['properties']['zipCode']['type']);
        $this->assertSame('core', $result['layer']);
        $this->assertNull($result['extends']);
        $this->assertSame([], $result['omit']);
        $this->assertSame(realpath($filePath) ?: $filePath, $result['sourceFile']);
    }

    /**
     * A /Pyz/ path should yield layer === 'project'.
     *
     * @return void
     */
    public function testGivenPyzPathObjectFileWhenLoadingThenDetectsProjectLayer(): void
    {
        // Arrange
        $content = <<<YAML
object:
    name: CustomerAddress
    properties:
        street: { type: string }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'customer-address.object.yml', '/Pyz/SomeModule/');
        $loader = $this->createLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath));

        // Assert
        $this->assertSame('project', $result['layer']);
        $this->assertSame('CustomerAddress', $result['name']);
    }

    /**
     * A /SprykerFeature/ path should yield layer === 'feature'.
     *
     * @return void
     */
    public function testGivenFeaturePathObjectFileWhenLoadingThenDetectsFeatureLayer(): void
    {
        // Arrange
        $content = <<<YAML
object:
    name: FeatureAddress
    properties:
        city: { type: str }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'feature-address.object.yml', '/SprykerFeature/SomeModule/');
        $loader = $this->createLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath));

        // Assert
        $this->assertSame('feature', $result['layer']);
        $this->assertSame('string', $result['properties']['city']['type']);
    }

    /**
     * extends and omit fields must pass through to the output array.
     *
     * @return void
     */
    public function testGivenExtendsAndOmitWhenLoadingThenCarriesThemThrough(): void
    {
        // Arrange
        $content = <<<YAML
object:
    name: ExtendedAddress
    extends: Address
    omit:
        - zipCode
        - country
    properties:
        street: { type: string }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'extended-address.object.yml', '/Spryker/SomeModule/');
        $loader = $this->createLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath));

        // Assert
        $this->assertSame('Address', $result['extends']);
        $this->assertSame(['zipCode', 'country'], $result['omit']);
        $this->assertSame('ExtendedAddress', $result['name']);
    }

    /**
     * int type alias should be normalized to integer.
     *
     * @return void
     */
    public function testGivenIntTypeAliasWhenLoadingThenNormalizesToInteger(): void
    {
        // Arrange
        $content = <<<YAML
object:
    name: Order
    properties:
        total: { type: int }
        status: { type: bool }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'order.object.yml', '/Spryker/SomeModule/');
        $loader = $this->createLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath));

        // Assert
        $this->assertSame('integer', $result['properties']['total']['type']);
        $this->assertSame('boolean', $result['properties']['status']['type']);
    }

    /**
     * A file missing the `object:` root key must throw ApiSchemaValidationException.
     *
     * @return void
     */
    public function testGivenFileWithoutObjectKeyWhenLoadingThenThrows(): void
    {
        // Arrange – YAML without an `object:` root key
        $content = <<<YAML
resource:
    name: SomeResource
YAML;
        $filePath = $this->createTmpObjectFile($content, 'bad.object.yml', '/Spryker/SomeModule/');
        $loader = $this->createLoader();

        // Assert
        $this->expectException(ApiSchemaValidationException::class);
        $this->expectExceptionMessageMatches('/must have an "object" key with a non-empty "name"/');

        // Act
        $loader->load(new SplFileInfo($filePath));
    }

    /**
     * A file with an `object:` key but an empty `name` must throw ApiSchemaValidationException.
     *
     * @return void
     */
    public function testGivenObjectWithEmptyNameWhenLoadingThenThrows(): void
    {
        // Arrange – `object:` present but `name` is empty string
        $content = <<<YAML
object:
    name: ''
    properties:
        foo: { type: string }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'empty-name.object.yml', '/Spryker/SomeModule/');
        $loader = $this->createLoader();

        // Assert
        $this->expectException(ApiSchemaValidationException::class);
        $this->expectExceptionMessageMatches('/must have an "object" key with a non-empty "name"/');

        // Act
        $loader->load(new SplFileInfo($filePath));
    }

    /**
     * A non-/Pyz/ path loaded with layerOverride='project' must report layer === 'project'.
     *
     * @return void
     */
    public function testGivenNonPyzPathWithProjectLayerOverrideWhenLoadingThenUsesOverriddenLayer(): void
    {
        // Arrange – a central-directory path has no /Pyz/ segment, so path detection alone would read core.
        $content = <<<YAML
object:
    name: Address
    properties:
        zipCode: { type: string }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'address.object.yml', '/config/api/objects/');
        $loader = $this->createLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath), 'project');

        // Assert
        $this->assertSame('project', $result['layer']);
        $this->assertSame('Address', $result['name']);
    }

    /**
     * Without a layerOverride, path detection still applies (a non-/Pyz/ path reads core).
     *
     * @return void
     */
    public function testGivenNonPyzPathWithoutLayerOverrideWhenLoadingThenDetectsLayerFromPath(): void
    {
        // Arrange
        $content = <<<YAML
object:
    name: Address
    properties:
        zipCode: { type: string }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'address.object.yml', '/config/api/objects/');
        $loader = $this->createLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath));

        // Assert
        $this->assertSame('core', $result['layer']);
    }

    /**
     * An invalid layerOverride (not one of core/feature/project) must fail loud rather than silently
     * sorting as core in the resolver.
     *
     * @return void
     */
    public function testGivenInvalidLayerOverrideWhenLoadingThenThrows(): void
    {
        // Arrange
        $content = <<<YAML
object:
    name: Address
    properties:
        zipCode: { type: string }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'address.object.yml', '/config/api/objects/');
        $loader = $this->createLoader();

        // Assert
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessageMatches('/layer/i');

        // Act
        $loader->load(new SplFileInfo($filePath), 'Project');
    }

    /**
     * A valid layerOverride (feature) must be accepted and reported verbatim.
     *
     * @return void
     */
    public function testGivenValidLayerOverrideWhenLoadingThenUsesOverriddenLayer(): void
    {
        // Arrange
        $content = <<<YAML
object:
    name: Address
    properties:
        zipCode: { type: string }
YAML;
        $filePath = $this->createTmpObjectFile($content, 'address.object.yml', '/config/api/objects/');
        $loader = $this->createLoader();

        // Act
        $result = $loader->load(new SplFileInfo($filePath), 'feature');

        // Assert
        $this->assertSame('feature', $result['layer']);
    }

    protected function createLoader(): ObjectSchemaLoaderInterface
    {
        /** @var \Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface $schemaParser */
        $schemaParser = $this->tester->getContainer()->get(SchemaParserInterface::class);

        return new ObjectSchemaLoader(new ValidationSchemaLoader(), $schemaParser);
    }

    /**
     * Create a temporary *.object.yml file whose real path contains $pathSegment.
     *
     * To embed a specific path segment (e.g. /Pyz/) in the real path the file is written into
     * a matching subdirectory under sys_get_temp_dir().
     */
    protected function createTmpObjectFile(string $content, string $filename, string $pathSegment): string
    {
        $dir = sys_get_temp_dir() . $pathSegment . uniqid('obj_', true);
        mkdir($dir, 0777, true);
        $path = $dir . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }
}
