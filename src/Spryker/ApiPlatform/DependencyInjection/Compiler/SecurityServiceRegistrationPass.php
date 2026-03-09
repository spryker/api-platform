<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use InvalidArgumentException;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Validates that SecurityBundle is registered when resource schemas use security expressions.
 *
 * This compiler pass runs at compile time and throws a clear error if any resource
 * YAML files contain `security:` expressions but the SecurityBundle is not registered.
 * This prevents cryptic runtime failures.
 */
class SecurityServiceRegistrationPass implements CompilerPassInterface
{
    protected const string SECURITY_BUNDLE_NAME = 'SecurityBundle';

    protected const string RESOURCE_FILE_PATTERN_YML = '*.resource.yml';

    protected const string RESOURCE_FILE_PATTERN_YAML = '*.resource.yaml';

    protected const string RESOURCE_API_PATH_FORMAT = 'resources/api/%s';

    public function process(ContainerBuilder $container): void
    {
        if ($this->isSecurityBundleRegistered($container)) {
            return;
        }

        $resourcesWithSecurity = $this->findResourcesWithSecurityExpressions($container);

        if ($resourcesWithSecurity === []) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The following API resource schemas use security expressions but SecurityBundle is not registered: %s. '
            . 'Register SecurityBundle in your bundles.php to enable security expression evaluation.',
            implode(', ', $resourcesWithSecurity),
        ));
    }

    protected function isSecurityBundleRegistered(ContainerBuilder $container): bool
    {
        $bundles = $container->getParameter('kernel.bundles');

        return is_array($bundles) && isset($bundles[static::SECURITY_BUNDLE_NAME]);
    }

    /**
     * @return array<string>
     */
    protected function findResourcesWithSecurityExpressions(ContainerBuilder $container): array
    {
        if (!$container->hasParameter('spryker_api_platform.api_types') || !$container->hasParameter('spryker_api_platform.source_directories')) {
            return [];
        }

        $apiTypes = $container->getParameter('spryker_api_platform.api_types');
        $sourceDirectories = $container->getParameter('spryker_api_platform.source_directories');

        if (!is_array($apiTypes) || !is_array($sourceDirectories)) {
            return [];
        }

        $resourcesWithSecurity = [];

        foreach ($apiTypes as $apiType) {
            $schemaFiles = $this->findSchemaFiles($sourceDirectories, (string)$apiType);

            foreach ($schemaFiles as $schemaFile) {
                if ($this->schemaHasSecurityExpressions($schemaFile)) {
                    $resourcesWithSecurity[] = $schemaFile->getFilename();
                }
            }
        }

        return $resourcesWithSecurity;
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<\SplFileInfo>
     */
    protected function findSchemaFiles(array $sourceDirectories, string $apiType): array
    {
        $apiType = strtolower($apiType);
        $searchDirectories = $this->getSearchDirectories($sourceDirectories, $apiType);

        if ($searchDirectories === []) {
            return [];
        }

        $schemaFiles = [];

        $finder = new Finder();
        $finder->files()
            ->in($searchDirectories)
            ->name(static::RESOURCE_FILE_PATTERN_YML)
            ->name(static::RESOURCE_FILE_PATTERN_YAML);

        foreach ($finder as $file) {
            $schemaFiles[] = $file;
        }

        return $schemaFiles;
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<string>
     */
    protected function getSearchDirectories(array $sourceDirectories, string $apiType): array
    {
        $directories = [];

        foreach ($sourceDirectories as $sourceDirectory) {
            if (!is_dir($sourceDirectory)) {
                continue;
            }

            try {
                $directoryFinder = new Finder();
                $directoryFinder
                    ->directories()
                    ->in($sourceDirectory)
                    ->name($apiType)
                    ->filter(function (SplFileInfo $file) use ($apiType): bool {
                        $path = $file->getRelativePathname();

                        return str_ends_with($path, sprintf(static::RESOURCE_API_PATH_FORMAT, $apiType));
                    });

                foreach ($directoryFinder as $directory) {
                    $directories[] = $directory->getRealPath();
                }
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $directories;
    }

    protected function schemaHasSecurityExpressions(SplFileInfo $schemaFile): bool
    {
        try {
            $schema = Yaml::parseFile($schemaFile->getPathname());

            if (!is_array($schema) || !isset($schema['resource'])) {
                return false;
            }

            $resource = $schema['resource'];

            if (isset($resource['security']) || isset($resource['securityPostDenormalize']) || isset($resource['securityPostValidation'])) {
                return true;
            }

            if (!isset($resource['operations'])) {
                return false;
            }

            foreach ($resource['operations'] as $operation) {
                if (!is_array($operation)) {
                    continue;
                }

                if (isset($operation['security']) || isset($operation['securityPostDenormalize']) || isset($operation['securityPostValidation'])) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
