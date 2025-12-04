<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Finder;

use Generator;
use InvalidArgumentException;
use SplFileInfo;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\Finder\Finder;

/**
 * Finds API schema files using Symfony Finder component.
 *
 * Searches across core, feature, and project layers for YAML schema files.
 * Uses generators for memory-efficient file discovery.
 */
class SchemaFinder implements SchemaFinderInterface
{
    /**
     * @var array<string> File extensions to search for
     */
    protected const array SCHEMA_EXTENSIONS = ['resource.yaml', 'resource.yml'];

    public function __construct(protected readonly ApiPlatformConfig $config)
    {
    }

    /**
     * Find all schema files for a given ApiType.
     *
     * The API type is normalized to lowercase to match the directory structure convention
     * where schema files are organized in lowercase directories (e.g., schemas/backoffice/).
     *
     * Searches in all configured source directories:
     * - src/Spryker/{Module}/resources/api/{apiType}/*.{yaml,yml}
     * - src/SprykerFeature/{Module}/resources/api/{apiType}/*.{yaml,yml}
     * - src/Pyz/{Module}/resources/api/{apiType}/*.{yaml,yml}
     *
     * @param string $apiType The API type (normalized to lowercase automatically)
     */
    public function findSchemaFiles(string $apiType): Generator
    {
        $apiType = ApiTypeNormalizer::normalizeForSchemaLookup($apiType);

        $directories = $this->getSearchDirectories($apiType);

        if ($directories === []) {
            return;
        }

        $finder = new Finder();
        $finder
            ->files()
            ->in($directories)
            ->name($this->getFileNamePatterns())
            ->sortByName();

        foreach ($finder as $file) {
            yield $file;
        }
    }

    /**
     * Get directories to search for the given ApiType.
     *
     * Only includes directories that actually exist.
     *
     * @return array<string>
     */
    protected function getSearchDirectories(string $apiType): array
    {
        $directories = [];
        $sourceDirectories = $this->config->getSourceDirectories();

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

                        return str_ends_with($path, sprintf('/resources/api/%s', $apiType));
                    });

                foreach ($directoryFinder as $directory) {
                    $directories[] = $directory->getRealPath();
                }
            } catch (InvalidArgumentException $e) {
                // Directory not found or not readable, skip
                continue;
            }
        }

        return $directories;
    }

    /**
     * Get diagnostic information about schema file search for troubleshooting.
     *
     * Provides detailed information about where the system searched for schema files,
     * which directories exist, and which were skipped. Useful for debugging configuration issues.
     *
     * @param string $apiType The API type to get diagnostics for
     *
     * @return array<string, mixed> Diagnostic information including:
     *   - api_type: The normalized API type
     *   - configured_sources: Array of configured source directories
     *   - search_pattern: Human-readable pattern showing where schemas are expected
     *   - searched_paths: All paths that were checked
     *   - existing_directories: Paths that exist and contain the api type directory
     *   - skipped_directories: Paths that were skipped because they don't exist
     *   - directories_found_count: Number of directories found containing schema files
     */
    public function getDiagnosticInfo(string $apiType): array
    {
        $apiType = ApiTypeNormalizer::normalizeForSchemaLookup($apiType);

        $sourceDirectories = $this->config->getSourceDirectories();
        $searchedPaths = [];
        $existingDirectories = [];
        $skippedDirectories = [];

        foreach ($sourceDirectories as $sourceDirectory) {
            $searchPath = sprintf('%s/{OrganizationName}/{ModuleName}/resources/api/%s', $sourceDirectory, $apiType);
            $searchedPaths[] = $searchPath;

            if (!is_dir($sourceDirectory)) {
                $skippedDirectories[] = $sourceDirectory;

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

                        return str_ends_with($path, sprintf('/resources/api/%s', $apiType));
                    });

                foreach ($directoryFinder as $directory) {
                    $existingDirectories[] = $directory->getRealPath();
                }
            } catch (InvalidArgumentException $e) {
                $skippedDirectories[] = $sourceDirectory;
            }
        }

        return [
            'api_type' => $apiType,
            'configured_sources' => $sourceDirectories,
            'search_pattern' => sprintf('{OrganizationName}/{ModuleName}/resources/api/%s', $apiType),
            'searched_paths' => $searchedPaths,
            'existing_directories' => $existingDirectories,
            'skipped_directories' => $skippedDirectories,
            'directories_found_count' => count($existingDirectories),
        ];
    }

    /**
     * @return array<string>
     */
    protected function getFileNamePatterns(): array
    {
        return array_map(
            fn (string $extension): string => '*.' . $extension,
            static::SCHEMA_EXTENSIONS,
        );
    }
}
