<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ApiPlatformResourceGenerationRequestTransfer;
use Psr\Log\NullLogger;
use ReflectionClass;
use SplFileInfo;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Generator\ResourceGenerator;
use Spryker\ApiPlatform\Generator\ResourceGeneratorInterface;
use Spryker\ApiPlatform\Schema\Object\Finder\ObjectSchemaFinderInterface;
use Spryker\ApiPlatform\Schema\Object\Loader\ObjectSchemaLoaderInterface;
use Spryker\ApiPlatform\Schema\Validation\Loader\ValidationSchemaLoaderInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group ResourceGeneratorTest
 * Add your own group annotations below this line
 */
class ResourceGeneratorTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenValidSchemaWhenGeneratingResourcesThenYieldsSuccess(): void
    {
        // Arrange
        $this->tester->createDirectoryStructure([
            'TestModule' => [
                'resources' => [
                    'api' => [
                        'Storefront' => [
                            'Customer.yaml' => $this->tester->createValidYamlSchemaContent('Customer', 'Storefront'),
                        ],
                    ],
                ],
            ],
        ]);
        $generator = $this->createResourceGenerator();

        // Act
        $request = (new ApiPlatformResourceGenerationRequestTransfer())
            ->setApiType('Storefront')
            ->setIsKeepExisting(true);
        $results = iterator_to_array($generator->generateResources($request));

        // Assert
        $this->assertNotEmpty($results);
    }

    public function testGivenNoSchemasWhenGeneratingResourcesThenYieldsNoResults(): void
    {
        // Arrange
        $generator = $this->createResourceGenerator();

        // Act
        $request = (new ApiPlatformResourceGenerationRequestTransfer())
            ->setApiType('NonExistent')
            ->setIsKeepExisting(true);
        $results = iterator_to_array($generator->generateResources($request));

        // Assert
        $this->assertCount(1, $results);
    }

    public function testGivenSchemaWithNestedObjectPropertyWhenGeneratingResourcesThenWritesNestedObjectClassFile(): void
    {
        // Arrange
        $yaml = <<<YAML
resource:
    name: Carts
    shortName: Carts
    description: "Test resource"

    operations:
        - type: Get
        - type: GetCollection

    properties:
        totals:
            type: object
            properties:
                grandTotal:
                    type: integer
                    description: "Final total"
YAML;
        $this->tester->createDirectoryStructure([
            'TestOrg' => [
                'TestModule' => [
                    'resources' => [
                        'api' => [
                            'storefront' => [
                                'Carts.resource.yaml' => $yaml,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $generator = $this->createResourceGenerator();

        // Act
        $request = (new ApiPlatformResourceGenerationRequestTransfer())
            ->setApiType('Storefront')
            ->setIsKeepExisting(true);
        iterator_to_array($generator->generateResources($request));

        // Assert — the companion value-object file is written into the per-resource owner subdirectory.
        $this->assertFileExists(sprintf('%s/Storefront/Carts/CartsTotalsStorefrontObject.php', sys_get_temp_dir()));
    }

    /**
     * @dataProvider objectValidationFileNameProvider
     */
    public function testGivenValidationFilenameWhenDerivingObjectNameThenReturnsPascalCase(string $fileName, string $expectedObjectName): void
    {
        // Arrange — the derivation helper only reads the filename stem, so it needs no constructor
        // collaborators; instantiate without the constructor and invoke the protected seam via reflection.
        $reflectionClass = new ReflectionClass(ResourceGenerator::class);
        $generator = $reflectionClass->newInstanceWithoutConstructor();
        $method = $reflectionClass->getMethod('deriveObjectNameFromValidationFile');

        // Act
        $objectName = $method->invoke($generator, new SplFileInfo($fileName));

        // Assert
        $this->assertSame($expectedObjectName, $objectName);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function objectValidationFileNameProvider(): array
    {
        return [
            'single-word .yml' => ['/path/objects/address.object.validation.yml', 'Address'],
            'hyphenated .yml' => ['/path/objects/address-snapshot.object.validation.yml', 'AddressSnapshot'],
            'single-word .yaml' => ['/path/objects/address.object.validation.yaml', 'Address'],
            'hyphenated .yaml' => ['/path/objects/address-snapshot.object.validation.yaml', 'AddressSnapshot'],
        ];
    }

    /**
     * Two validation files resolving to the same objectName within the same layer (one module file,
     * one central-directory file, both project) must fail loud naming both source files.
     */
    public function testGivenTwoValidationFilesSameObjectNameSameLayerWhenLoadingThenThrowsNamingBoth(): void
    {
        // Arrange — a module file under /Pyz/ (project) and a central file (project) for the same object.
        $modulePath = '/Pyz/SomeModule/resources/api/storefront/objects/address.object.validation.yml';
        $centralPath = '/project/root/config/api/objects/storefront/address.object.validation.yml';

        $generator = $this->createResourceGeneratorWithValidationFiles(
            moduleFilePaths: [$modulePath],
            centralFilePaths: [$centralPath],
        );

        // Assert
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote('address.object.validation.yml', '/') . '/');

        // Act
        $this->invokeLoadObjectValidationSchemas($generator);
    }

    /**
     * The same objectName across DIFFERENT layers (a feature-layer module file and a project-layer
     * central file) is the legitimate override and must NOT throw.
     */
    public function testGivenTwoValidationFilesSameObjectNameDifferentLayersWhenLoadingThenDoesNotThrow(): void
    {
        // Arrange — a SprykerFeature file (feature) and a central file (project) for the same object.
        $featurePath = '/SprykerFeature/SomeModule/resources/api/storefront/objects/address.object.validation.yml';
        $centralPath = '/project/root/config/api/objects/storefront/address.object.validation.yml';

        $generator = $this->createResourceGeneratorWithValidationFiles(
            moduleFilePaths: [$featurePath],
            centralFilePaths: [$centralPath],
        );

        // Act
        $result = $this->invokeLoadObjectValidationSchemas($generator);

        // Assert — single resolved entry for the objectName, no exception.
        $this->assertArrayHasKey('Address', $result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function invokeLoadObjectValidationSchemas(ResourceGenerator $generator): array
    {
        $reflectionClass = new ReflectionClass(ResourceGenerator::class);
        $method = $reflectionClass->getMethod('loadObjectValidationSchemas');

        /** @var array<string, mixed> $result */
        $result = $method->invoke($generator, 'Storefront');

        return $result;
    }

    /**
     * Builds a ResourceGenerator whose object schema finder returns the given module / central validation
     * files, with a validation loader that returns a trivial parsed array for any file.
     *
     * @param array<string> $moduleFilePaths
     * @param array<string> $centralFilePaths
     */
    protected function createResourceGeneratorWithValidationFiles(array $moduleFilePaths, array $centralFilePaths): ResourceGenerator
    {
        $objectSchemaFinder = $this->makeEmpty(ObjectSchemaFinderInterface::class, [
            'findObjectValidationSchemas' => function () use ($moduleFilePaths): iterable {
                foreach ($moduleFilePaths as $path) {
                    yield new SplFileInfo($path);
                }
            },
            'findCentralObjectValidationSchemas' => function () use ($centralFilePaths): iterable {
                foreach ($centralFilePaths as $path) {
                    yield new SplFileInfo($path);
                }
            },
        ]);

        $validationSchemaLoader = $this->makeEmpty(ValidationSchemaLoaderInterface::class, [
            'load' => ['post' => ['name' => ['NotBlank' => []]]],
        ]);

        /** @var \Spryker\ApiPlatform\Schema\Object\Loader\ObjectSchemaLoaderInterface $objectSchemaLoader */
        $objectSchemaLoader = $this->tester->getContainer()->get(ObjectSchemaLoaderInterface::class);

        $reflectionClass = new ReflectionClass(ResourceGenerator::class);

        /** @var \Spryker\ApiPlatform\Generator\ResourceGenerator $generator */
        $generator = $reflectionClass->newInstanceWithoutConstructor();

        $this->setProperty($generator, 'objectSchemaFinder', $objectSchemaFinder);
        $this->setProperty($generator, 'objectSchemaLoader', $objectSchemaLoader);
        $this->setProperty($generator, 'validationSchemaLoader', $validationSchemaLoader);
        $this->setProperty($generator, 'logger', new NullLogger());

        return $generator;
    }

    protected function setProperty(object $object, string $property, mixed $value): void
    {
        $reflectionProperty = (new ReflectionClass($object))->getProperty($property);
        $reflectionProperty->setValue($object, $value);
    }

    protected function createResourceGenerator(): ResourceGeneratorInterface
    {
        $config = new ApiPlatformConfig(
            sourceDirectories: [$this->tester->getVirtualFilesystemPath()],
            cacheDir: sys_get_temp_dir(),
            generatedDir: sys_get_temp_dir(),
            apiTypes: ['Storefront'],
            debug: false,
        );

        $this->tester->getContainer()->set(ApiPlatformConfig::class, $config);

        return $this->tester->getContainer()->get(ResourceGenerator::class);
    }
}
