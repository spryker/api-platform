<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use Spryker\ApiPlatform\Schema\Directory\ApiDirectoryLocator;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tracks the API schema files as container resources, so in debug mode a schema edit, a new schema
 * file or a new schema directory makes the container stale.
 */
class ApiSchemaResourcePass implements CompilerPassInterface
{
    protected const string SOURCE_DIRECTORIES_PARAMETER = 'spryker_api_platform.source_directories';

    protected const string API_TYPES_PARAMETER = 'spryker_api_platform.api_types';

    protected const string CANONICAL_OBJECT_SEARCH_DIRECTORIES_PARAMETER = 'spryker_api_platform.canonical_object_search_directories';

    protected const string SCHEMA_FILE_PATTERN = '/\.ya?ml$/';

    public function __construct(protected ApiDirectoryLocator $apiDirectoryLocator = new ApiDirectoryLocator())
    {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(static::SOURCE_DIRECTORIES_PARAMETER) || !$container->hasParameter(static::API_TYPES_PARAMETER)) {
            return;
        }

        $sourceDirectories = array_map('strval', (array)$container->getParameter(static::SOURCE_DIRECTORIES_PARAMETER));

        foreach ((array)$container->getParameter(static::API_TYPES_PARAMETER) as $apiType) {
            $this->addSchemaDirectoryResources($container, $sourceDirectories, (string)$apiType);
            $this->addCanonicalObjectDirectoryResources($container, (string)$apiType);
        }
    }

    /**
     * @param array<string> $sourceDirectories
     */
    protected function addSchemaDirectoryResources(ContainerBuilder $container, array $sourceDirectories, string $apiType): void
    {
        foreach ($this->apiDirectoryLocator->locateResourceSchemaDirectories($sourceDirectories, $apiType) as $schemaDirectory) {
            $container->addResource(new DirectoryResource($schemaDirectory, static::SCHEMA_FILE_PATTERN));
        }

        foreach (array_filter($sourceDirectories, 'is_dir') as $sourceDirectory) {
            $this->addSchemaDirectoryGlobResources($container, rtrim($sourceDirectory, '/'), $apiType);
        }
    }

    protected function addSchemaDirectoryGlobResources(ContainerBuilder $container, string $sourceDirectory, string $apiType): void
    {
        foreach ($this->apiDirectoryLocator->getResourceSchemaDirectoryGlobPatterns($apiType) as $globPattern) {
            $container->addResource(new GlobResource($sourceDirectory, $globPattern, true));
        }
    }

    protected function addCanonicalObjectDirectoryResources(ContainerBuilder $container, string $apiType): void
    {
        foreach ($this->getCanonicalObjectSearchDirectories($container, $apiType) as $directory) {
            if (!is_dir($directory)) {
                $container->addResource(new FileExistenceResource($directory));

                continue;
            }

            $container->addResource(new DirectoryResource($directory, static::SCHEMA_FILE_PATTERN));
        }
    }

    /**
     * @return array<string>
     */
    protected function getCanonicalObjectSearchDirectories(ContainerBuilder $container, string $apiType): array
    {
        if (!$container->hasParameter(static::CANONICAL_OBJECT_SEARCH_DIRECTORIES_PARAMETER)) {
            return [];
        }

        $canonicalObjectSearchDirectories = (array)$container->getParameter(static::CANONICAL_OBJECT_SEARCH_DIRECTORIES_PARAMETER);

        return array_map('strval', (array)($canonicalObjectSearchDirectories[ApiTypeNormalizer::normalizeForSchemaLookup($apiType)] ?? []));
    }
}
