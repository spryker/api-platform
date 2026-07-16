<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Cache;

use Generated\Shared\Transfer\ApiPlatformResourceGenerationRequestTransfer;
use Spryker\ApiPlatform\Cache\ApiResourceCacheWarmer;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Generator\ResourceGeneratorInterface;
use SprykerTest\ApiPlatform\ApiUnitTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Cache
 * @group ApiResourceCacheWarmerTest
 * Add your own group annotations below this line
 */
class ApiResourceCacheWarmerTest extends ApiUnitTestCase
{
    public function testGivenGenerationYieldsErrorResultWhenWarmingUpThenThrows(): void
    {
        // Arrange
        $apiType = 'storefront';
        $errorMessage = 'Duplicate objectName "CartItem" defined in two resources';

        $config = $this->createMock(ApiPlatformConfig::class);
        $config->method('getApiTypes')->willReturn([$apiType]);

        $generator = $this->createMock(ResourceGeneratorInterface::class);
        $generator->method('generateResources')->willReturnCallback(
            function (ApiPlatformResourceGenerationRequestTransfer $request) use ($errorMessage): \Generator {
                yield ['status' => 'error', 'message' => $errorMessage];
            },
        );

        $filesystem = $this->createMock(Filesystem::class);

        $warmer = new ApiResourceCacheWarmer($generator, $config, $filesystem);

        // Assert
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($apiType, '/') . '/');
        $this->expectExceptionMessageMatches('/' . preg_quote($errorMessage, '/') . '/');

        // Act
        $warmer->warmUp('/cache');
    }

    public function testGivenAllResultsSucceedWhenWarmingUpThenReturnsWarmedFilesWithoutThrowing(): void
    {
        // Arrange
        $apiType = 'storefront';
        $generatedFile = '/var/generated/storefront/SomeResource.php';
        $outputDir = '/var/generated/storefront';

        $config = $this->createMock(ApiPlatformConfig::class);
        $config->method('getApiTypes')->willReturn([$apiType]);
        $config->method('getApiResourceDirectory')->with('Storefront')->willReturn($outputDir);

        $generator = $this->createMock(ResourceGeneratorInterface::class);
        $generator->method('generateResources')->willReturnCallback(
            function (ApiPlatformResourceGenerationRequestTransfer $request) use ($generatedFile): \Generator {
                yield ['status' => 'success', 'file' => $generatedFile];
            },
        );

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->with($outputDir)->willReturn(true);

        $warmer = new ApiResourceCacheWarmer($generator, $config, $filesystem);

        // Act
        $warmedFiles = $warmer->warmUp('/cache');

        // Assert
        $this->assertSame([$generatedFile], $warmedFiles);
    }
}
