<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

use Generator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Generator\Result\MergeResult;
use Spryker\ApiPlatform\Generator\Result\ParseResult;
use Spryker\ApiPlatform\Generator\Result\ResourceParseResult;
use Spryker\ApiPlatform\Generator\Result\ValidationParseResult;
use Spryker\ApiPlatform\Generator\Result\ValidationResult;
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
        protected LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return \Generator<array{status: string, resource?: string, file?: string, message?: string, diagnostics?: array<string, mixed>, suggestion?: string}>
     */
    public function generateResources(string $apiType): Generator
    {
        $apiType = $this->prepareGeneration($apiType);

        $parseResult = $this->parseSchemas($apiType);

        foreach ($parseResult->getFailedSchemaFiles() as $failure) {
            yield [
                'status' => 'error',
                'message' => sprintf(
                    'Failed to process schema file %s: %s',
                    $failure['file'],
                    $failure['error'],
                ),
            ];
        }

        $mergeResult = $this->mergeResourceSchemas($parseResult->getGroupedSchemas(), $apiType);

        foreach ($mergeResult->getFailedMerges() as $failure) {
            yield [
                'status' => 'error',
                'message' => sprintf(
                    'Failed to merge resource %s: %s',
                    $failure['resource'],
                    $failure['error'],
                ),
            ];
        }

        $validationResult = $this->validateMergedSchemas($mergeResult->getMergedSchemas());

        foreach ($validationResult->getFailedValidations() as $failure) {
            yield [
                'status' => 'error',
                'message' => sprintf(
                    'Validation failed for resource %s: %s',
                    $failure['resource'],
                    $failure['error'],
                ),
            ];
        }

        $generatedCount = 0;

        foreach ($this->generateResourceFiles($validationResult->getValidatedSchemas(), $apiType) as $result) {
            if ($result['status'] === 'generated') {
                $generatedCount++;
            }

            yield $result;
        }

        if ($generatedCount === 0) {
            $diagnostics = $this->schemaFinder->getDiagnosticInfo($apiType);
            $validationDiagnostics = $this->validationSchemaFinder->getValidationDiagnosticInfo($apiType);

            yield [
                'status' => 'error',
                'message' => 'No resources were generated',
                'diagnostics' => [
                    ...$diagnostics,
                    'failed_schema_files' => $parseResult->getFailedSchemaFiles(),
                    'failed_merges' => $mergeResult->getFailedMerges(),
                    'failed_validations' => $validationResult->getFailedValidations(),
                    'failed_validation_files' => $parseResult->getFailedValidationFiles(),
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
     * @return array<\SplFileInfo>
     */
    protected function loadValidationSchemas(string $apiType): array
    {
        $validationFiles = [];

        foreach ($this->validationSchemaFinder->findAllValidationSchemas($apiType) as $file) {
            $validationFiles[] = $file;
        }

        $this->logger->debug(sprintf(
            "Found %d validation schema file(s) for API type '%s'",
            count($validationFiles),
            $apiType,
        ));

        foreach ($validationFiles as $file) {
            $this->logger->debug(sprintf(
                '  - %s',
                $file->getPathname(),
            ));
        }

        return $validationFiles;
    }

    /**
     * @param array<\SplFileInfo> $validationFiles
     */
    protected function parseValidationSchemas(array $validationFiles, string $apiType): ValidationParseResult
    {
        $validationSchemas = [];
        $failedFiles = [];

        foreach ($validationFiles as $file) {
            try {
                $schema = $this->validationSchemaLoader->load($file);
                $filePath = $file->getRealPath() ?: $file->getPathname();

                $key = $this->generateValidationKey($filePath, $apiType);

                if (!isset($validationSchemas[$key])) {
                    $validationSchemas[$key] = [];
                }

                $validationSchemas[$key][] = [
                    'schema' => $schema,
                    'sourceFile' => $filePath,
                ];
            } catch (Throwable $exception) {
                $failedFiles[] = [
                    'file' => $file->getPathname(),
                    'error' => $exception->getMessage(),
                ];

                continue;
            }
        }

        return new ValidationParseResult(
            validationSchemas: $validationSchemas,
            failedFiles: $failedFiles,
        );
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

        $fileName = basename($filePath, '.validation.yml');
        $fileName = basename($fileName, '.validation.yaml');

        return sprintf('%s_%s', $apiType, $fileName);
    }

    protected function prepareGeneration(string $apiType): string
    {
        $normalizedApiType = ApiTypeNormalizer::normalizeForGeneration($apiType);

        $this->logger->debug(sprintf(
            'Preparing generation for API type: %s (normalized: %s)',
            $apiType,
            $normalizedApiType,
        ));

        $this->cleanOutputDirectory($normalizedApiType);

        return $normalizedApiType;
    }

    /**
     * @return array<\SplFileInfo>
     */
    protected function loadResourceSchemas(string $apiType): array
    {
        $schemaFiles = [];

        foreach ($this->schemaFinder->findSchemaFiles($apiType) as $file) {
            $schemaFiles[] = $file;
        }

        $this->logger->debug(sprintf(
            "Found %d schema file(s) for API type '%s'",
            count($schemaFiles),
            $apiType,
        ));

        foreach ($schemaFiles as $file) {
            $this->logger->debug(sprintf(
                '  - %s',
                $file->getPathname(),
            ));
        }

        return $schemaFiles;
    }

    protected function parseSchemas(string $apiType): ParseResult
    {
        $validationFiles = $this->loadValidationSchemas($apiType);

        $validationParseResult = $this->parseValidationSchemas($validationFiles, $apiType);

        $totalValidationFiles = count($validationFiles);
        $totalValidationSchemas = count($validationParseResult->getValidationSchemas());

        $this->logger->debug(sprintf(
            "Parsed %d validation file(s) into %d validation schema(s) for API type '%s'",
            $totalValidationFiles,
            $totalValidationSchemas,
            $apiType,
        ));

        $schemaFiles = $this->loadResourceSchemas($apiType);

        $resourceParseResult = $this->parseResourceSchemas($schemaFiles, $validationParseResult->getValidationSchemas());

        $this->logger->debug(sprintf(
            'Total: %d validation schema(s), %d resource schema(s)',
            $totalValidationSchemas,
            count($resourceParseResult->getGroupedSchemas()),
        ));

        return new ParseResult(
            groupedSchemas: $resourceParseResult->getGroupedSchemas(),
            failedValidationFiles: $validationParseResult->getFailedFiles(),
            failedSchemaFiles: $resourceParseResult->getFailedFiles(),
        );
    }

    /**
     * @param array<\SplFileInfo> $schemaFiles
     * @param array<string, mixed> $validationSchemas
     */
    protected function parseResourceSchemas(array $schemaFiles, array $validationSchemas): ResourceParseResult
    {
        $resourceSchemas = [];
        $failedFiles = [];

        foreach ($schemaFiles as $file) {
            $this->logger->debug(sprintf(
                'Parsing schema file: %s',
                $file->getPathname(),
            ));

            try {
                $rawSchema = $this->loadSchema($file);
                $parsedSchema = $this->schemaParser->parse($rawSchema, $file, $validationSchemas);

                $resourceKey = $this->generateResourceKey($file);

                if (!isset($resourceSchemas[$resourceKey])) {
                    $resourceSchemas[$resourceKey] = [];
                }

                $resourceSchemas[$resourceKey][] = $parsedSchema;
            } catch (Throwable $exception) {
                $failedFiles[] = [
                    'file' => $file->getPathname(),
                    'error' => $exception->getMessage(),
                ];

                $this->logger->error(sprintf(
                    'Failed to parse schema file %s: %s',
                    $file->getPathname(),
                    $exception->getMessage(),
                ));
            }
        }

        $totalSchemaFiles = count($schemaFiles);
        $totalResources = count($resourceSchemas);

        $this->logger->debug(sprintf(
            'Parsed %d schema file(s) into %d resource(s)',
            $totalSchemaFiles,
            $totalResources,
        ));

        return new ResourceParseResult(
            groupedSchemas: $resourceSchemas,
            failedFiles: $failedFiles,
        );
    }

    /**
     * @param array<string, array<array<string, mixed>>> $groupedSchemas
     */
    protected function mergeResourceSchemas(array $groupedSchemas, string $apiType): MergeResult
    {
        $mergedSchemas = [];
        $failedMerges = [];

        foreach ($groupedSchemas as $resourceKey => $schemas) {
            $this->logger->debug(sprintf(
                "Merging %d schema(s) for resource '%s'",
                count($schemas),
                $resourceKey,
            ));

            try {
                $mergedSchema = $this->schemaMerger->merge($schemas, $resourceKey, $apiType);
                $mergedSchemas[$resourceKey] = $mergedSchema;
            } catch (Throwable $exception) {
                $failedMerges[] = [
                    'resource' => $resourceKey,
                    'error' => $exception->getMessage(),
                ];

                $this->logger->error(sprintf(
                    "Failed to merge schemas for resource '%s': %s",
                    $resourceKey,
                    $exception->getMessage(),
                ));
            }
        }

        $this->logger->debug(sprintf(
            'Successfully merged %d resource(s)',
            count($mergedSchemas),
        ));

        return new MergeResult(
            mergedSchemas: $mergedSchemas,
            failedMerges: $failedMerges,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $mergedSchemas
     */
    protected function validateMergedSchemas(array $mergedSchemas): ValidationResult
    {
        $validatedSchemas = [];
        $failedValidations = [];

        foreach ($mergedSchemas as $resourceKey => $mergedSchema) {
            $this->logger->debug(sprintf(
                "Validating merged schema for resource '%s'",
                $resourceKey,
            ));

            try {
                $this->schemaValidator->validatePostMerge($mergedSchema);
                $validatedSchemas[$resourceKey] = $mergedSchema;
            } catch (Throwable $exception) {
                $failedValidations[] = [
                    'resource' => $resourceKey,
                    'error' => $exception->getMessage(),
                ];

                $this->logger->error(sprintf(
                    "Schema validation failed for resource '%s': %s",
                    $resourceKey,
                    $exception->getMessage(),
                ));
            }
        }

        $this->logger->debug(sprintf(
            'Successfully validated %d resource(s)',
            count($validatedSchemas),
        ));

        return new ValidationResult(
            validatedSchemas: $validatedSchemas,
            failedValidations: $failedValidations,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $validatedSchemas
     *
     * @return \Generator<array{status: string, resource?: string, file?: string, className?: string, sourceFiles?: array<string>, validationSourceFiles?: array<string>, message?: string}>
     */
    protected function generateResourceFiles(array $validatedSchemas, string $apiType): Generator
    {
        foreach ($validatedSchemas as $resourceKey => $mergedSchema) {
            $resourceName = $mergedSchema['name'] ?? $mergedSchema['shortName'] ?? $resourceKey;

            $this->logger->debug(sprintf(
                "Generating resource file for '%s'",
                $resourceName,
            ));

            try {
                $generatedCode = $this->classGenerator->generate($mergedSchema, $apiType);
                $filePath = $this->writeResourceFile($resourceName, $apiType, $generatedCode);

                $className = sprintf('%s%sResource', $resourceName, $apiType);
                $sourceFiles = $this->extractSourceFilesFromMetadata($mergedSchema);

                $this->logger->info(sprintf(
                    'Generated %s at %s',
                    $className,
                    $filePath,
                ));

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
                $this->logger->error(sprintf(
                    "Failed to generate resource file for '%s': %s",
                    $resourceName,
                    $exception->getMessage(),
                ));

                yield [
                    'status' => 'error',
                    'message' => sprintf(
                        'Failed to generate resource %s: %s',
                        $resourceKey,
                        $exception->getMessage(),
                    ),
                ];
            }
        }
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function extractSourceFilesFromMetadata(array $schema): array
    {
        $sourceFiles = [];

        if (!isset($schema['_metadata']['contributingSources']) || !is_array($schema['_metadata']['contributingSources'])) {
            return $sourceFiles;
        }

        foreach ($schema['_metadata']['contributingSources'] as $source) {
            if (isset($source['files']) && is_array($source['files'])) {
                $sourceFiles = array_merge($sourceFiles, $source['files']);

                continue;
            }

            if (isset($source['file'])) {
                $sourceFiles[] = $source['file'];
            }
        }

        return $sourceFiles;
    }
}
