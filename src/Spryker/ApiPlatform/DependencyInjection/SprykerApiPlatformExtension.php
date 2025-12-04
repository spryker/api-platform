<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Dependency Injection Extension for API Generator Bundle
 *
 * Loads service definitions and processes configuration.
 */
class SprykerApiPlatformExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->ensureGeneratedDirectoriesExist($container, $config['generated_dir'], $config['api_types']);
        $this->ensureAssetsDirectoryExist($container);
    }

    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $projectDir = $container->getParameter('kernel.project_dir');

        if (!is_string($projectDir)) {
            $projectDir = '';
        }

        $sourceDirectories = $this->resolveDirectoryPaths($config['source_directories'], $projectDir);

        $container->setParameter('spryker_api_platform.source_directories', $sourceDirectories);
        $container->setParameter('spryker_api_platform.cache_dir', $config['cache_dir']);
        $container->setParameter('spryker_api_platform.generated_dir', $config['generated_dir']);
        $container->setParameter('spryker_api_platform.api_types', $config['api_types']);
        $container->setParameter('spryker_api_platform.debug', $config['debug']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../../resources/config'));
        $loader->load('services.php');
    }

    /**
     * @param array<string> $directories
     *
     * @return array<string>
     */
    protected function resolveDirectoryPaths(array $directories, string $projectDir): array
    {
        $resolved = [];

        foreach ($directories as $directory) {
            if ($this->isAbsolutePath($directory)) {
                $resolved[] = $directory;

                continue;
            }

            $resolved[] = sprintf('%s/%s', rtrim($projectDir, '/'), ltrim($directory, '/'));
        }

        return $resolved;
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Z]:/i', $path) === 1;
    }

    /**
     * @param array<string> $apiTypes
     */
    protected function ensureGeneratedDirectoriesExist(ContainerBuilder $container, string $generatedDir, array $apiTypes): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');

        if (!is_string($projectDir)) {
            return;
        }

        // When the extensions are loaded, the configuration is not resolved. The paths may still contain parameter placeholders.
        $generatedDir = str_replace('%kernel.project_dir%', $projectDir, $generatedDir);

        foreach ($apiTypes as $apiType) {
            $directory = sprintf('%s/%s', $generatedDir, ucfirst($apiType));

            if (is_dir($directory)) {
                continue;
            }

            mkdir($directory, 0755, true);
        }
    }

    /**
     * We need to ensure that for the currently running application the assets directory exists. This is needed for the
     * `assets:install` command to work properly.
     */
    protected function ensureAssetsDirectoryExist(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');

        if (!is_string($projectDir)) {
            return;
        }

        $applicationName = $this->toCamelCase(APPLICATION);

        $assetsDirectory = sprintf('%s/public/%s/assets/', $projectDir, $applicationName);

        if (!is_dir($assetsDirectory)) {
            mkdir($assetsDirectory, 0755, true);
        }
    }

    protected function toCamelCase(string $string): string
    {
        $applicationFragments = explode('_', $string);
        $applicationFragments = array_map(function ($fragment) {
            return ucfirst(strtolower($fragment));
        }, $applicationFragments);

        return implode('', $applicationFragments);
    }
}
