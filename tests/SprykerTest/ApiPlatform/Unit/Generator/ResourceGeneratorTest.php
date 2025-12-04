<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Generator\ClassGenerator;
use Spryker\ApiPlatform\Generator\ResourceGenerator;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use Spryker\ApiPlatform\Schema\Finder\SchemaFinder;
use Spryker\ApiPlatform\Schema\Loader\YamlSchemaLoader;
use Spryker\ApiPlatform\Schema\Merger\SchemaMerger;
use Spryker\ApiPlatform\Schema\Parser\SchemaParser;
use Spryker\ApiPlatform\Schema\Validation\Finder\ValidationSchemaFinder;
use Spryker\ApiPlatform\Schema\Validation\Loader\ValidationSchemaLoader;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapper;
use Spryker\ApiPlatform\Schema\Validation\Merger\ValidationSchemaMerger;
use Spryker\ApiPlatform\Schema\Validator\Rules\MergeValidationRule;
use Spryker\ApiPlatform\Schema\Validator\SchemaValidator;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\Filesystem\Filesystem;

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
        $results = iterator_to_array($generator->generateResources('Storefront', true));

        // Assert
        $this->assertNotEmpty($results);
    }

    public function testGivenNoSchemasWhenGeneratingResourcesThenYieldsNoResults(): void
    {
        // Arrange
        $generator = $this->createResourceGenerator();

        // Act
        $results = iterator_to_array($generator->generateResources('NonExistent', true));

        // Assert
        $this->assertCount(1, $results);
    }

    protected function createResourceGenerator(): ResourceGenerator
    {
        $config = new ApiPlatformConfig(
            sourceDirectories: [$this->tester->getVirtualFilesystemPath()],
            cacheDir: sys_get_temp_dir(),
            generatedDir: sys_get_temp_dir(),
            apiTypes: ['Storefront'],
            debug: false,
        );

        return new ResourceGenerator(
            new SchemaFinder($config),
            [new YamlSchemaLoader()],
            new SchemaParser(),
            new SchemaValidator([], new MergeValidationRule()),
            new SchemaMerger(new ValidationSchemaMerger()),
            new ClassGenerator(new PhpTemplateRenderer(), new ValidationGroupMapper()),
            $config,
            new ValidationSchemaFinder($config),
            new ValidationSchemaLoader(),
            new Filesystem(),
        );
    }
}
