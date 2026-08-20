<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use SplFileInfo;
use Spryker\ApiPlatform\Schema\Directory\ApiDirectoryLocator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Shared schema file discovery and parsing used by multiple compiler passes.
 *
 * Locates resource schema files (*.resource.yml/yaml) in `resources/api/{apiType}/`
 * directories and parses their YAML content into resource definitions.
 *
 * Discovery results are memoized per (source directories, API type) pair — several
 * compiler passes request the same files during a single container build, and the
 * bundle shares one instance across all of them so the filesystem is only hit once.
 */
class SchemaFileDiscovery
{
    /**
     * @var array<string, array<\SplFileInfo>>
     */
    protected array $schemaFileCache = [];

    public function __construct(protected ApiDirectoryLocator $apiDirectoryLocator = new ApiDirectoryLocator())
    {
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<\SplFileInfo>
     */
    public function findSchemaFiles(array $sourceDirectories, string $apiType): array
    {
        $apiType = strtolower($apiType);
        $cacheKey = md5(serialize([$sourceDirectories, $apiType]));

        if (isset($this->schemaFileCache[$cacheKey])) {
            return $this->schemaFileCache[$cacheKey];
        }

        $searchDirectories = $this->apiDirectoryLocator->locateResourceSchemaDirectories($sourceDirectories, $apiType);

        if ($searchDirectories === []) {
            return $this->schemaFileCache[$cacheKey] = [];
        }

        $schemaFiles = [];

        $finder = new Finder();
        $finder->files()
            ->in($searchDirectories)
            ->name('*.resource.yml')
            ->name('*.resource.yaml')
            ->sortByName();

        foreach ($finder as $file) {
            $schemaFiles[] = $file;
        }

        return $this->schemaFileCache[$cacheKey] = $schemaFiles;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseSchemaFile(SplFileInfo $schemaFile): ?array
    {
        try {
            $schema = Yaml::parseFile($schemaFile->getPathname());

            if (!is_array($schema)) {
                return null;
            }

            return $schema;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Extracts resource definitions from both singular and plural YAML formats.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractResourceDefinitions(array $schema): array
    {
        $resources = [];

        if (isset($schema['resource']) && is_array($schema['resource'])) {
            $resources[] = $schema['resource'];
        }

        if (isset($schema['resources']) && is_array($schema['resources'])) {
            foreach ($schema['resources'] as $resourceEntry) {
                if (is_array($resourceEntry)) {
                    $resources[] = $resourceEntry;
                }
            }
        }

        return $resources;
    }
}
