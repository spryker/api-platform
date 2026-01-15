<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validation\Finder;

use Generator;
use InvalidArgumentException;
use SplFileInfo;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\Finder\Finder;

class ValidationSchemaFinder implements ValidationSchemaFinderInterface
{
    protected const string VALIDATION_FILE_SUFFIX = '.validation.yml';

    protected const array VALIDATION_EXTENSIONS = ['validation.yml', 'validation.yaml'];

    public function __construct(protected readonly ApiPlatformConfig $config)
    {
    }

    /**
     * Finds a validation schema file for a specific resource.
     *
     * The API type is normalized to lowercase to match the directory structure convention.
     *
     * @param string $resourceName The resource name
     * @param string $apiType The API type (normalized to lowercase automatically)
     * @param string $layer The layer (core, feature, project)
     * @param string $sourceDirectory The source directory to search in
     *
     * @return \SplFileInfo|null The validation schema file or null if not found
     */
    public function findValidationSchema(
        string $resourceName,
        string $apiType,
        string $layer,
        string $sourceDirectory,
    ): ?SplFileInfo {
        $apiType = ApiTypeNormalizer::normalizeForSchemaLookup($apiType);

        $pattern = sprintf(
            '%s/resources/api/%s/%s%s',
            $sourceDirectory,
            $apiType,
            strtolower($resourceName),
            static::VALIDATION_FILE_SUFFIX,
        );

        if (file_exists($pattern)) {
            return new SplFileInfo($pattern);
        }

        return null;
    }

    /**
     * Finds all validation schema files for a given API type.
     *
     * The API type is normalized to lowercase to match the directory structure convention.
     *
     * @param string $apiType The API type (normalized to lowercase automatically)
     */
    public function findAllValidationSchemas(string $apiType): Generator
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

                        return str_ends_with($path, sprintf('resources/api/%s', $apiType));
                    });

                foreach ($directoryFinder as $directory) {
                    $directories[] = $directory->getRealPath();
                }
            } catch (InvalidArgumentException $e) {
                continue;
            }
        }

        return $directories;
    }

    /**
     * Get diagnostic information about validation schema search for troubleshooting.
     *
     * Provides detailed information about where the system searched for validation schema files,
     * which directories exist, and which were skipped. Useful for debugging configuration issues.
     *
     * @param string $apiType The API type to get diagnostics for
     *
     * @return array<string, mixed> Diagnostic information including:
     *   - api_type: The normalized API type
     *   - configured_sources: Array of configured source directories
     *   - search_pattern: Human-readable pattern showing where validation schemas are expected
     *   - searched_paths: All paths that were checked
     *   - existing_directories: Paths that exist and contain the api type directory
     *   - skipped_directories: Paths that were skipped because they don't exist
     *   - directories_found_count: Number of directories found containing validation schema files
     */
    public function getValidationDiagnosticInfo(string $apiType): array
    {
        $apiType = ApiTypeNormalizer::normalizeForSchemaLookup($apiType);

        $sourceDirectories = $this->config->getSourceDirectories();
        $searchedPaths = [];
        $existingDirectories = [];
        $skippedDirectories = [];

        foreach ($sourceDirectories as $sourceDirectory) {
            $searchPath = sprintf('%s/{OrganizationName}/{ModuleName}/resources/api/%s/*.validation.yml', $sourceDirectory, $apiType);
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
            'search_pattern' => sprintf('{OrganizationName}/{ModuleName}/resources/api/%s/*.validation.yml', $apiType),
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
            static::VALIDATION_EXTENSIONS,
        );
    }
}
