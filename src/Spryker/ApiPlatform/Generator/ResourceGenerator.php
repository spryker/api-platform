<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Generator;
use SplFileInfo;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Schema\Finder\SchemaFinderInterface;
use Spryker\ApiPlatform\Schema\Merger\SchemaMergerInterface;
use Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface;
use Spryker\ApiPlatform\Schema\Validation\Finder\ValidationSchemaFinderInterface;
use Spryker\ApiPlatform\Schema\Validation\Loader\ValidationSchemaLoaderInterface;
use Spryker\ApiPlatform\Schema\Validator\SchemaValidatorInterface;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Spryker\ApiPlatform\Utility\ResourceNameNormalizer;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

class ResourceGenerator implements ResourceGeneratorInterface
{
    /**
     * @param iterable<\Spryker\ApiPlatform\Schema\Loader\SchemaLoaderInterface> $loaders
     */
    public function __construct(
        protected readonly SchemaFinderInterface $schemaFinder,
        protected readonly iterable $loaders,
        protected readonly SchemaParserInterface $schemaParser,
        protected readonly SchemaValidatorInterface $schemaValidator,
        protected readonly SchemaMergerInterface $schemaMerger,
        protected readonly ClassGeneratorInterface $classGenerator,
        protected readonly ApiPlatformConfig $config,
        protected readonly ValidationSchemaFinderInterface $validationSchemaFinder,
        protected readonly ValidationSchemaLoaderInterface $validationSchemaLoader,
        protected readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @return \Generator<array{status: string, resource?: string, file?: string, message?: string, diagnostics?: array<string, mixed>, suggestion?: string}>
     */
    public function generateResources(string $apiType): Generator
    {
        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

        $this->cleanOutputDirectory($apiType);

        $sourceFiles = [];
        $resourceSchemas = [];
        $failedSchemaFiles = [];
        $failedValidationFiles = [];

        $validationSchemas = $this->loadAllValidationSchemas($apiType, $failedValidationFiles);

        foreach ($this->schemaFinder->findSchemaFiles($apiType) as $file) {
            $sourceFiles[] = $file->getRealPath() ?: $file->getPathname();

            try {
                $rawSchema = $this->loadSchema($file);
                $parsedSchema = $this->schemaParser->parse($rawSchema, $file, $validationSchemas);

                $resourceKey = $this->generateResourceKey($file);

                if (!isset($resourceSchemas[$resourceKey])) {
                    $resourceSchemas[$resourceKey] = [];
                }

                $resourceSchemas[$resourceKey][] = $parsedSchema;
            } catch (Throwable $exception) {
                $failedSchemaFiles[] = [
                    'file' => $file->getPathname(),
                    'error' => $exception->getMessage(),
                ];

                yield [
                    'status' => 'error',
                    'message' => sprintf(
                        'Failed to process schema file %s: %s',
                        $file->getPathname(),
                        $exception->getMessage(),
                    ),
                ];

                continue;
            }
        }

        $generatedCount = 0;

        foreach ($resourceSchemas as $resourceKey => $schemas) {
            try {
                $mergedSchema = $this->schemaMerger->merge($schemas, $resourceKey, $apiType);
                $this->schemaValidator->validatePostMerge($mergedSchema);

                $resourceName = $mergedSchema['name'] ?? $mergedSchema['shortName'] ?? $resourceKey;

                $generatedCode = $this->classGenerator->generate($mergedSchema, $apiType);
                $filePath = $this->writeResourceFile($resourceName, $apiType, $generatedCode);

                $generatedCount++;

                $className = sprintf('%s%sResource', $resourceName, $apiType);
                $sourceFiles = [];

                foreach ($schemas as $schema) {
                    if (isset($schema['sourceFile'])) {
                        $sourceFiles[] = $schema['sourceFile'];
                    }
                }

                yield [
                    'status' => 'generated',
                    'resource' => $resourceName,
                    'file' => $filePath,
                    'className' => $className,
                    'sourceFiles' => $sourceFiles,
                    'validationSourceFiles' => $mergedSchema['validationSourceFiles'] ?? [],
                    'message' => sprintf('Generated %s%sResource', $resourceName, $apiType),
                ];
            } catch (Throwable $exception) {
                yield [
                    'status' => 'error',
                    'message' => sprintf(
                        'Failed to generate resource %s: %s',
                        $resourceKey,
                        $exception->getMessage(),
                    ),
                ];

                continue;
            }
        }

        if ($generatedCount === 0) {
            $diagnostics = $this->schemaFinder->getDiagnosticInfo($apiType);
            $validationDiagnostics = $this->validationSchemaFinder->getValidationDiagnosticInfo($apiType);

            yield [
                'status' => 'error',
                'message' => 'No resources were generated',
                'diagnostics' => [
                    ...$diagnostics,
                    'failed_schema_files' => $failedSchemaFiles,
                    'total_files_found' => count($sourceFiles),
                    'failed_validation_files' => $failedValidationFiles,
                    'validation_diagnostics' => $validationDiagnostics,
                ],
                'suggestion' => sprintf(
                    'Check your configuration in config/{APPLICATION}/packages/spryker_api_platform.php. Verify that source directories are correctly configured and contain schema files matching the pattern: %s',
                    $diagnostics['search_pattern'] ?? sprintf('*/{OrganizationName}/{ModuleName}/resources/api/%s', $apiType),
                ),
            ];

            return;
        }
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     *
     * @return array<string, mixed>
     */
    protected function loadSchema(SplFileInfo $file): array
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($file)) {
                return $loader->load($file);
            }
        }

        throw new ApiSchemaGenerationException(
            sprintf('No loader found for file: %s', $file->getPathname()),
        );
    }

    protected function cleanOutputDirectory(string $apiType): void
    {
        $outputDir = $this->config->getApiResourceDirectory($apiType);

        if (!is_dir($outputDir)) {
            return;
        }

        $this->filesystem->remove($outputDir);
    }

    protected function writeResourceFile(string $resourceName, string $apiType, string $generatedCode): string
    {
        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);
        $resourceName = ResourceNameNormalizer::normalize($resourceName);

        $outputDir = $this->config->getApiResourceDirectory($apiType);
        $fileName = sprintf('%s%sResource.php', $resourceName, $apiType);
        $filePath = sprintf('%s/%s', $outputDir, $fileName);

        $this->filesystem->dumpFile($filePath, $generatedCode);

        return $filePath;
    }

    /**
     * @param array<array{file: string, error: string}> $failedValidationFiles
     *
     * @return array<string, array{schema: array<string, mixed>, sourceFile: string}>
     */
    protected function loadAllValidationSchemas(string $apiType, array &$failedValidationFiles = []): array
    {
        $validationSchemas = [];

        foreach ($this->validationSchemaFinder->findAllValidationSchemas($apiType) as $file) {
            try {
                $schema = $this->validationSchemaLoader->load($file);
                $filePath = $file->getRealPath() ?: $file->getPathname();

                $key = $this->generateValidationKey($filePath, $apiType);

                $validationSchemas[$key] = [
                    'schema' => $schema,
                    'sourceFile' => $filePath,
                ];
            } catch (Throwable $exception) {
                $failedValidationFiles[] = [
                    'file' => $file->getPathname(),
                    'error' => $exception->getMessage(),
                ];

                continue;
            }
        }

        return $validationSchemas;
    }

    protected function generateResourceKey(SplFileInfo $file): string
    {
        $fileName = basename($file->getPathname(), '.resource.yml');
        $fileName = basename($fileName, '.resource.yaml');

        return $fileName;
    }

    protected function generateValidationKey(string $filePath, string $apiType): string
    {
        $apiType = ApiTypeNormalizer::normalizeForSchemaLookup($apiType);

        $layer = $this->detectSourceLayer($filePath);

        $fileName = basename($filePath, '.validation.yml');
        $fileName = basename($fileName, '.validation.yaml');

        return sprintf('%s_%s_%s', $apiType, $layer, $fileName);
    }

    protected function detectSourceLayer(string $filePath): string
    {
        if (str_contains($filePath, '/Spryker/')) {
            return 'core';
        }

        if (str_contains($filePath, '/SprykerFeature/')) {
            return 'feature';
        }

        return 'project';
    }
}
