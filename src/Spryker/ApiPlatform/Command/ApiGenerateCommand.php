<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Command;

use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Generator\ResourceGeneratorInterface;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ApiGenerateCommand extends Command
{
    protected const string NAME = 'api:generate';

    protected const int CODE_SUCCESS = 0;

    protected const int CODE_ERROR = 1;

    public function __construct(
        protected readonly ResourceGeneratorInterface $resourceGenerator,
        protected readonly ApiPlatformConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(static::NAME)
            ->setDescription('Generate API resources from schema files')
            ->addArgument(
                'api-type',
                InputArgument::OPTIONAL,
                'The ApiType to generate (e.g., Storefront, Backend)',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be generated without writing files',
            )
            ->addOption(
                'validate-only',
                null,
                InputOption::VALUE_NONE,
                'Only validate schemas without generating',
            )
            ->addOption(
                'resource',
                'r',
                InputOption::VALUE_REQUIRED,
                'Generate only specific resource',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apiTypes = $this->resolveApiTypes($input, $io);

        if ($apiTypes === []) {
            $io->error('No ApiTypes configured. Please specify an apiType argument.');

            return static::CODE_ERROR;
        }

        $isDryRun = $input->getOption('dry-run');
        $isValidateOnly = $input->getOption('validate-only');
        $resourceFilter = $input->getOption('resource');

        $totalApiTypes = count($apiTypes);
        $failedApiTypes = [];

        foreach ($apiTypes as $index => $apiType) {
            if ($totalApiTypes > 1) {
                $io->section(sprintf('Processing API Type %d of %d: %s', $index + 1, $totalApiTypes, $apiType));
            }

            $exitCode = $this->generateForApiType(
                $apiType,
                $isDryRun,
                $isValidateOnly,
                $resourceFilter,
                $io,
                $output,
            );

            if ($exitCode === static::CODE_ERROR) {
                $failedApiTypes[] = $apiType;
            }

            if ($totalApiTypes > 1 && $index < $totalApiTypes - 1) {
                $io->newLine();
            }
        }

        if ($failedApiTypes !== []) {
            $io->newLine();
            $io->error(sprintf(
                'Generation failed for %d API Type(s): %s',
                count($failedApiTypes),
                implode(', ', $failedApiTypes),
            ));

            return static::CODE_ERROR;
        }

        if ($totalApiTypes > 1) {
            $io->newLine();
            $io->success(sprintf('All %d API Types generated successfully!', $totalApiTypes));
        }

        return static::CODE_SUCCESS;
    }

    protected function generateForApiType(
        string $apiType,
        bool $isDryRun,
        bool $isValidateOnly,
        ?string $resourceFilter,
        SymfonyStyle $io,
        OutputInterface $output,
    ): int {
        $verbosity = $output->getVerbosity();

        if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
            $headerParts = [sprintf('Generating API resources for ApiType: %s', $apiType)];

            if ($isDryRun) {
                $headerParts[] = '(dry-run)';
            }

            if ($isValidateOnly) {
                $headerParts[] = '(validate-only)';
            }

            $io->title(implode(' ', $headerParts));
        }

        $generator = $this->resourceGenerator->generateResources($apiType);

        $generatedResources = [];
        $errorCount = 0;
        $errors = [];

        foreach ($generator as $result) {
            $status = $result['status'];

            if ($status === 'generated') {
                if ($isValidateOnly && $generatedResources === []) {
                    if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                        $io->success('Validation completed successfully!');
                    }

                    return static::CODE_SUCCESS;
                }

                $generatedResources[] = [
                    'resource' => $result['resource'] ?? 'Unknown',
                    'file' => $result['file'] ?? '',
                    'className' => $result['className'] ?? '',
                    'sourceFiles' => $result['sourceFiles'] ?? [],
                    'validationSourceFiles' => $result['validationSourceFiles'] ?? [],
                ];
            }

            if ($status === 'error') {
                $errorCount++;
                $errors[] = $result;
            }
        }

        if ($errorCount > 0) {
            $io->error(sprintf('Generation failed with %d error(s):', $errorCount));

            foreach ($errors as $error) {
                $io->writeln(sprintf('  - %s', $error['message'] ?? 'Unknown error'));

                if (isset($error['diagnostics'])) {
                    $this->displayDiagnostics($io, $error['diagnostics']);
                }

                if (isset($error['suggestion'])) {
                    $io->newLine();
                    $io->writeln(sprintf('  <comment>%s</comment>', $error['suggestion']));
                }
            }

            return static::CODE_ERROR;
        }

        if ($isDryRun && $verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
            $io->writeln(sprintf('Would generate: <info>%d</info> file(s)', count($generatedResources)));
            $io->newLine();
            $io->success('Dry-run completed!');

            return static::CODE_SUCCESS;
        }

        $this->displayGenerationResults($generatedResources, $verbosity, $io, $output);

        return static::CODE_SUCCESS;
    }

    /**
     * @param array<array{resource: string, file: string, className: string, sourceFiles: array<string>, validationSourceFiles: array<string>}> $generatedResources
     */
    protected function displayGenerationResults(
        array $generatedResources,
        int $verbosity,
        SymfonyStyle $io,
        OutputInterface $output,
    ): void {
        if ($verbosity === OutputInterface::VERBOSITY_NORMAL) {
            return;
        }

        if ($verbosity === OutputInterface::VERBOSITY_VERBOSE) {
            $io->newLine();
            $io->writeln(sprintf('Generated: <info>%d</info> file(s)', count($generatedResources)));
            $io->newLine();
            $io->success('Done!');

            return;
        }

        if ($verbosity === OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $io->newLine();
            $io->writeln('Generated files:');

            foreach ($generatedResources as $resource) {
                $io->writeln(sprintf('  - %s', $resource['file']));
            }

            $io->newLine();
            $io->success('Done!');

            return;
        }

        if ($verbosity >= OutputInterface::VERBOSITY_DEBUG) {
            $io->newLine();

            foreach ($generatedResources as $resource) {
                $io->writeln(sprintf('Resource: <info>%s</info>', $resource['resource']));
                $io->writeln(sprintf('  Class: <comment>%s</comment>', $resource['className']));

                if ($resource['sourceFiles'] !== []) {
                    $io->writeln('  Schema files:');

                    foreach ($resource['sourceFiles'] as $sourceFile) {
                        $io->writeln(sprintf('    - %s', $sourceFile));
                    }
                }

                if ($resource['validationSourceFiles'] !== []) {
                    $io->writeln('  Validation files:');

                    foreach ($resource['validationSourceFiles'] as $validationFile) {
                        $io->writeln(sprintf('    - %s', $validationFile));
                    }
                } else {
                    $io->writeln('  Validation files: (none)');
                }

                $io->writeln(sprintf('  Generated: <info>%s</info>', $resource['file']));
                $io->newLine();
            }

            $io->success('Done!');
        }
    }

    /**
     * Resolves which API types should be processed based on user input.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     *
     * @return array<string> Array of API types to process
     */
    protected function resolveApiTypes(InputInterface $input, SymfonyStyle $io): array
    {
        $apiType = $input->getArgument('api-type');

        if ($apiType === null) {
            return $this->config->getApiTypes();
        }

        $normalizedType = $this->normalizeApiType($io, $apiType);

        return [$normalizedType];
    }

    /**
     * Normalizes user-provided API type with case-insensitive matching.
     *
     * If the user input does not match any configured type, an interactive selection is shown.
     *
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io The console input/output interface
     * @param string $userInput The user-provided API type (any casing)
     *
     * @return string The properly cased API type from configuration
     */
    protected function normalizeApiType(SymfonyStyle $io, string $userInput): string
    {
        $configuredTypes = $this->config->getApiTypes();

        $matchedType = ApiTypeNormalizer::findMatchingConfiguredType($userInput, $configuredTypes);

        if ($matchedType === null) {
            return $io->choice(
                sprintf('There is no API Type "%s" configured. Select one of the following:', $userInput),
                $configuredTypes,
            );
        }

        return $matchedType;
    }

    /**
     * Display diagnostic information for troubleshooting schema generation issues.
     *
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param array<string, mixed> $diagnostics
     *
     * @return void
     */
    protected function displayDiagnostics(SymfonyStyle $io, array $diagnostics): void
    {
        $io->newLine();
        $io->writeln('  <info>Diagnostics:</info>');

        if (isset($diagnostics['api_type'])) {
            $io->writeln(sprintf('    API Type: <comment>%s</comment>', $diagnostics['api_type']));
        }

        if (isset($diagnostics['configured_sources']) && $diagnostics['configured_sources'] !== []) {
            $io->writeln('    Configured source directories:');

            foreach ($diagnostics['configured_sources'] as $source) {
                $io->writeln(sprintf('      - %s', $source));
            }
        }

        if (isset($diagnostics['search_pattern'])) {
            $io->writeln(sprintf('    Search pattern: <comment>%s</comment>', $diagnostics['search_pattern']));
        }

        if (isset($diagnostics['directories_found_count'])) {
            $io->writeln(sprintf('    Directories found: <comment>%d</comment>', $diagnostics['directories_found_count']));
        }

        if (isset($diagnostics['total_files_found'])) {
            $io->writeln(sprintf('    Schema files found: <comment>%d</comment>', $diagnostics['total_files_found']));
        }

        if (isset($diagnostics['existing_directories']) && $diagnostics['existing_directories'] !== []) {
            $io->writeln('    Existing directories searched:');

            foreach ($diagnostics['existing_directories'] as $dir) {
                $io->writeln(sprintf('      - %s', $dir));
            }
        }

        if (isset($diagnostics['skipped_directories']) && $diagnostics['skipped_directories'] !== []) {
            $io->writeln('    Skipped directories (not found):');

            foreach ($diagnostics['skipped_directories'] as $dir) {
                $io->writeln(sprintf('      - %s', $dir));
            }
        }

        if (isset($diagnostics['failed_schema_files']) && $diagnostics['failed_schema_files'] !== []) {
            $io->writeln(sprintf('    Failed schema files: <comment>%d</comment>', count($diagnostics['failed_schema_files'])));

            foreach ($diagnostics['failed_schema_files'] as $failure) {
                $io->writeln(sprintf('      - %s', $failure['file'] ?? 'Unknown file'));
                $io->writeln(sprintf('        Error: %s', $failure['error'] ?? 'Unknown error'));
            }
        }

        if (isset($diagnostics['failed_validation_files']) && $diagnostics['failed_validation_files'] !== []) {
            $io->writeln(sprintf('    Failed validation files: <comment>%d</comment>', count($diagnostics['failed_validation_files'])));

            foreach ($diagnostics['failed_validation_files'] as $failure) {
                $io->writeln(sprintf('      - %s', $failure['file'] ?? 'Unknown file'));
                $io->writeln(sprintf('        Error: %s', $failure['error'] ?? 'Unknown error'));
            }
        }

        if (isset($diagnostics['validation_diagnostics'])) {
            $validationDiag = $diagnostics['validation_diagnostics'];

            $io->newLine();
            $io->writeln('  <info>Validation Schema Diagnostics:</info>');

            if (isset($validationDiag['search_pattern'])) {
                $io->writeln(sprintf('    Search pattern: <comment>%s</comment>', $validationDiag['search_pattern']));
            }

            if (isset($validationDiag['directories_found_count'])) {
                $io->writeln(sprintf('    Validation directories found: <comment>%d</comment>', $validationDiag['directories_found_count']));
            }

            if (isset($validationDiag['existing_directories']) && $validationDiag['existing_directories'] !== []) {
                $io->writeln('    Existing validation directories:');

                foreach ($validationDiag['existing_directories'] as $dir) {
                    $io->writeln(sprintf('      - %s', $dir));
                }
            }
        }
    }
}
