<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Object\Finder;

use Generator;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Schema\Directory\ApiDirectoryLocator;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\Finder\Finder;

class ObjectSchemaFinder implements ObjectSchemaFinderInterface
{
    protected const string OBJECTS_SUBDIR = 'objects';

    protected const string OBJECT_VALIDATION_SUFFIX = '.object.validation.';

    protected const array OBJECT_EXTENSIONS = ['object.yml', 'object.yaml'];

    protected const array OBJECT_VALIDATION_EXTENSIONS = ['object.validation.yml', 'object.validation.yaml'];

    public function __construct(
        protected readonly ApiPlatformConfig $config,
        protected ApiDirectoryLocator $apiDirectoryLocator = new ApiDirectoryLocator(),
    ) {
    }

    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findObjectSchemas(string $apiType): Generator
    {
        $apiType = ApiTypeNormalizer::normalizeForSchemaLookup($apiType);

        yield from $this->findSchemaFilesFromDirectories($this->getSearchDirectories($apiType), false);
    }

    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findObjectValidationSchemas(string $apiType): Generator
    {
        $apiType = ApiTypeNormalizer::normalizeForSchemaLookup($apiType);

        yield from $this->findSchemaFilesFromDirectories($this->getSearchDirectories($apiType), true);
    }

    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findCentralObjectSchemas(string $apiType): Generator
    {
        yield from $this->findSchemaFilesFromDirectories($this->getExistingCentralDirectories($apiType), false);
    }

    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findCentralObjectValidationSchemas(string $apiType): Generator
    {
        yield from $this->findSchemaFilesFromDirectories($this->getExistingCentralDirectories($apiType), true);
    }

    /**
     * @param array<string> $directories
     *
     * @return \Generator<\SplFileInfo>
     */
    protected function findSchemaFilesFromDirectories(array $directories, bool $validationFiles): Generator
    {
        if ($directories === []) {
            return;
        }

        $patterns = $validationFiles
            ? $this->getObjectValidationFileNamePatterns()
            : $this->getObjectFileNamePatterns();

        $finder = new Finder();
        $finder
            ->files()
            ->in($directories)
            ->name($patterns)
            ->sortByName();

        foreach ($finder as $file) {
            if ($this->isExcluded((string)$file->getRealPath())) {
                continue;
            }

            if (!$validationFiles && str_contains($file->getFilename(), static::OBJECT_VALIDATION_SUFFIX)) {
                continue;
            }

            yield $file;
        }
    }

    /**
     * @return array<string>
     */
    protected function getExistingCentralDirectories(string $apiType): array
    {
        $directories = [];

        foreach ($this->config->getCanonicalObjectSearchDirectories($apiType) as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $directories[] = $directory;
        }

        return $directories;
    }

    protected function isExcluded(string $realPath): bool
    {
        foreach ($this->config->getExcludedPathFragments() as $fragment) {
            if ($fragment !== '' && str_contains($realPath, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    protected function getSearchDirectories(string $apiType): array
    {
        $directories = [];
        $schemaDirectories = $this->apiDirectoryLocator->locateResourceSchemaDirectories(
            $this->config->getSourceDirectories(),
            $apiType,
        );

        foreach ($schemaDirectories as $schemaDirectory) {
            $objectsDir = sprintf('%s/%s', $schemaDirectory, static::OBJECTS_SUBDIR);

            if (!is_dir($objectsDir)) {
                continue;
            }

            $directories[] = $objectsDir;
        }

        return $directories;
    }

    /**
     * @return array<string>
     */
    protected function getObjectFileNamePatterns(): array
    {
        return array_map(
            fn (string $extension): string => '*.' . $extension,
            static::OBJECT_EXTENSIONS,
        );
    }

    /**
     * @return array<string>
     */
    protected function getObjectValidationFileNamePatterns(): array
    {
        return array_map(
            fn (string $extension): string => '*.' . $extension,
            static::OBJECT_VALIDATION_EXTENSIONS,
        );
    }
}
