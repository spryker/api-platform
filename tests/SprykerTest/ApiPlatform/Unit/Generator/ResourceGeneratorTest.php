<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ApiPlatformResourceGenerationRequestTransfer;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Generator\ResourceGenerator;
use Spryker\ApiPlatform\Generator\ResourceGeneratorInterface;
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
