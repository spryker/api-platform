<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\DependencyInjection\SprykerApiPlatformExtension;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group SprykerApiPlatformExtensionTest
 * Add your own group annotations below this line
 */
class SprykerApiPlatformExtensionTest extends Unit
{
    protected ApiUnitTester $tester;

    protected const string PROJECT_DIR = '/project/root';

    /**
     * A capitalized api-type key in canonical_object_search_directories must be normalized when stored,
     * so the lowercased lookup in the config getter resolves it for both casings.
     */
    public function testGivenCapitalizedApiTypeKeyWhenLoadingThenGetterResolvesForBothCasings(): void
    {
        // Arrange
        $config = $this->loadExtension([
            'canonical_object_search_directories' => [
                'Storefront' => ['/abs/objects/storefront'],
            ],
        ]);

        // Act
        $forCapitalized = $config->getCanonicalObjectSearchDirectories('Storefront');
        $forLowercase = $config->getCanonicalObjectSearchDirectories('storefront');

        // Assert
        $this->assertSame(['/abs/objects/storefront'], $forCapitalized);
        $this->assertSame(['/abs/objects/storefront'], $forLowercase);
    }

    /**
     * A %kernel.project_dir% placeholder in a canonical search directory must be expanded to the project
     * dir exactly once, leaving no literal placeholder and no doubled project dir.
     */
    public function testGivenProjectDirPlaceholderDirectoryWhenLoadingThenResolvesPlaceholderExactlyOnce(): void
    {
        // Arrange
        $config = $this->loadExtension([
            'canonical_object_search_directories' => [
                'storefront' => ['%kernel.project_dir%/config/api/objects/storefront'],
            ],
        ]);

        // Act
        $directories = $config->getCanonicalObjectSearchDirectories('storefront');

        // Assert
        $this->assertSame(
            [static::PROJECT_DIR . '/config/api/objects/storefront'],
            $directories,
        );
        $this->assertStringNotContainsString('%kernel.project_dir%', $directories[0]);
    }

    /**
     * A plain relative path must still be prepended with the project dir.
     */
    public function testGivenRelativeDirectoryWhenLoadingThenPrependsProjectDir(): void
    {
        // Arrange
        $config = $this->loadExtension([
            'canonical_object_search_directories' => [
                'storefront' => ['config/api/objects/storefront'],
            ],
        ]);

        // Act
        $directories = $config->getCanonicalObjectSearchDirectories('storefront');

        // Assert
        $this->assertSame(
            [static::PROJECT_DIR . '/config/api/objects/storefront'],
            $directories,
        );
    }

    /**
     * An absolute path must be left untouched.
     */
    public function testGivenAbsoluteDirectoryWhenLoadingThenLeavesItUntouched(): void
    {
        // Arrange
        $config = $this->loadExtension([
            'canonical_object_search_directories' => [
                'storefront' => ['/abs/config/api/objects/storefront'],
            ],
        ]);

        // Act
        $directories = $config->getCanonicalObjectSearchDirectories('storefront');

        // Assert
        $this->assertSame(['/abs/config/api/objects/storefront'], $directories);
    }

    public function testGivenContractCoverageExcludedResourcesConfigWhenLoadingThenTheContainerParameterIsSet(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', static::PROJECT_DIR);
        $container->setParameter('kernel.bundles', []);

        $extension = new SprykerApiPlatformExtension();

        // Act
        $extension->load([['contract_coverage_excluded_resources' => ['wishlists']]], $container);

        // Assert
        $this->assertSame(
            ['wishlists'],
            $container->getParameter('spryker_api_platform.contract_coverage_excluded_resources'),
        );
    }

    public function testGivenEnforcedDimensionsConfigWhenLoadingThenTheContainerParameterIsSet(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', static::PROJECT_DIR);
        $container->setParameter('kernel.bundles', []);

        // Act
        (new SprykerApiPlatformExtension())->load([['contract_coverage_enforced_dimensions' => ['error-codes', 'all']]], $container);

        // Assert
        $this->assertSame(
            ['error-codes', 'all'],
            $container->getParameter('spryker_api_platform.contract_coverage_enforced_dimensions'),
        );
    }

    public function testGivenABaselineConfigWhenLoadingThenTheContainerParameterKeepsItsItemKeysVerbatim(): void
    {
        // Arrange - item keys carry spaces, slashes and dashes, which a normalised array node would rewrite.
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', static::PROJECT_DIR);
        $container->setParameter('kernel.bundles', []);
        $baseline = ['includes' => ['GET /product-offers/{productOfferReference}  include product-offer-prices' => 'The prices include is never loaded.']];

        // Act
        (new SprykerApiPlatformExtension())->load([['contract_coverage_baseline' => $baseline]], $container);

        // Assert
        $this->assertSame($baseline, $container->getParameter('spryker_api_platform.contract_coverage_baseline'));
    }

    public function testGivenABaselineThatIsNotAMapWhenCompilingTheConfigurationThenItFails(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', static::PROJECT_DIR);
        $container->setParameter('kernel.bundles', []);

        // Assert
        $this->expectException(InvalidConfigurationException::class);

        // Act
        (new SprykerApiPlatformExtension())->load([['contract_coverage_baseline' => 'includes']], $container);
    }

    public function testGivenAnUnknownEnforcedDimensionWhenCompilingTheConfigurationThenItFails(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', static::PROJECT_DIR);
        $container->setParameter('kernel.bundles', []);

        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"error-code"');

        // Act
        (new SprykerApiPlatformExtension())->load([['contract_coverage_enforced_dimensions' => ['error-code']]], $container);
    }

    /**
     * Loads the extension with the given config and returns an ApiPlatformConfig wired with the resolved
     * canonical_object_search_directories parameter.
     *
     * @param array<string, mixed> $config
     */
    protected function loadExtension(array $config): ApiPlatformConfig
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', static::PROJECT_DIR);
        $container->setParameter('kernel.bundles', []);

        $extension = new SprykerApiPlatformExtension();
        $extension->load([$config], $container);

        /** @var array<string, array<string>> $resolved */
        $resolved = $container->getParameter('spryker_api_platform.canonical_object_search_directories');

        return new ApiPlatformConfig(
            sourceDirectories: [],
            cacheDir: '',
            generatedDir: '',
            apiTypes: [],
            debug: false,
            excludedPathFragments: [],
            canonicalObjectSearchDirectories: $resolved,
        );
    }
}
