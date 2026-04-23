<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use InvalidArgumentException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Shared schema file discovery and parsing used by multiple compiler passes.
 *
 * Locates resource schema files (*.resource.yml/yaml) in `resources/api/{apiType}/`
 * directories and parses their YAML content into resource definitions.
 */
class SchemaFileDiscovery
{
    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<\SplFileInfo>
     */
    public function findSchemaFiles(array $sourceDirectories, string $apiType): array
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
            ->name('*.resource.yml')
            ->name('*.resource.yaml')
            ->sortByName();

        foreach ($finder as $file) {
            $schemaFiles[] = $file;
        }

        return $schemaFiles;
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

                        return str_ends_with($path, sprintf('resources/api/%s', $apiType));
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
}
