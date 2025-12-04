<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Command;

use SplFileInfo;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\Schema\Finder\SchemaFinderInterface;
use Spryker\ApiPlatform\Schema\Merger\SchemaMergerInterface;
use Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface;
use Spryker\ApiPlatform\Schema\Validator\SchemaValidatorInterface;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class ApiDebugCommand extends Command
{
    protected const string NAME = 'api:debug';

    protected const int CODE_SUCCESS = 0;

    protected const int CODE_ERROR = 1;

    protected const array LAYER_LABELS = [
        'core' => 'CORE',
        'feature' => 'FEATURE',
        'project' => 'PROJECT',
    ];

    /**
     * @param iterable<\Spryker\ApiPlatform\Schema\Loader\SchemaLoaderInterface> $loaders
     */
    public function __construct(
        protected readonly SchemaFinderInterface $schemaFinder,
        protected readonly iterable $loaders,
        protected readonly SchemaParserInterface $schemaParser,
        protected readonly SchemaValidatorInterface $schemaValidator,
        protected readonly SchemaMergerInterface $schemaMerger,
        protected readonly ApiPlatformConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(static::NAME)
            ->setDescription('Debug and inspect API resources')
            ->addArgument(
                'resource',
                InputArgument::OPTIONAL,
                'The resource name to inspect',
            )
            ->addOption(
                'api-type',
                't',
                InputOption::VALUE_REQUIRED,
                'Filter to specific ApiType',
            )
            ->addOption(
                'list',
                'l',
                InputOption::VALUE_NONE,
                'List all resources across all ApiTypes',
            )
            ->addOption(
                'show-merged',
                'm',
                InputOption::VALUE_NONE,
                'Display final merged schema in YAML format',
            )
            ->addOption(
                'show-sources',
                's',
                InputOption::VALUE_NONE,
                'List all source files with priority',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $resourceName = $input->getArgument('resource');
        $apiType = $input->getOption('api-type');
        $isList = $input->getOption('list');
        $isShowMerged = $input->getOption('show-merged');
        $isShowSources = $input->getOption('show-sources');

        if ($apiType !== null) {
            $apiType = $this->normalizeApiType($io, $apiType);
        }

        if ($isList) {
            return $this->listAllResources($io, $apiType);
        }

        if ($resourceName === null) {
            $io->error('Please specify a resource name or use --list to show all resources.');

            return static::CODE_ERROR;
        }

        if ($apiType === null) {
            $apiTypes = $this->config->getApiTypes();

            if ($apiTypes === []) {
                $io->error('No ApiTypes configured. Please specify --api-type option.');

                return static::CODE_ERROR;
            }

            if (count($apiTypes) > 1) {
                $io->error('Multiple ApiTypes configured. Please specify --api-type option.');

                return static::CODE_ERROR;
            }

            $apiType = $apiTypes[0];
        }

        $schemas = $this->findSchemasForResource($resourceName, $apiType);

        if ($schemas === []) {
            $io->error(sprintf('Resource "%s" not found for ApiType "%s".', $resourceName, $apiType));

            return static::CODE_ERROR;
        }

        if ($isShowSources) {
            return $this->showSources($io, $resourceName, $apiType, $schemas);
        }

        $mergedSchema = $this->schemaMerger->merge($schemas, $resourceName, $apiType);

        $validationStatus = $this->getValidationStatus($mergedSchema);

        if ($isShowMerged) {
            return $this->showMergedSchema($io, $resourceName, $apiType, $mergedSchema, $validationStatus);
        }

        return $this->showResourceDetails($io, $resourceName, $apiType, $schemas, $mergedSchema, $validationStatus);
    }

    protected function listAllResources(SymfonyStyle $io, ?string $apiTypeFilter): int
    {
        $io->title('API Resources');

        $apiTypes = $apiTypeFilter !== null ? [$apiTypeFilter] : $this->discoverApiTypes();

        foreach ($apiTypes as $apiType) {
            $resources = $this->discoverResourcesForApiType($apiType);

            if ($resources === []) {
                continue;
            }

            $io->section(sprintf('ApiType: %s (%d resources)', $apiType, count($resources)));

            $tableRows = [];

            foreach ($resources as $resourceName => $count) {
                $tableRows[] = [$resourceName, $count];
            }

            $io->table(['Resource', 'Schema Files'], $tableRows);
        }

        return static::CODE_SUCCESS;
    }

    /**
     * @param array<array<string, mixed>> $schemas
     */
    protected function showSources(SymfonyStyle $io, string $resourceName, string $apiType, array $schemas): int
    {
        $io->title(sprintf('Source Files for Resource: %s (%s)', $resourceName, $apiType));

        $sortedSchemas = $this->sortSchemasByLayer($schemas);

        $io->writeln('Source Files (priority order):');
        $io->newLine();

        foreach ($sortedSchemas as $schema) {
            $layer = $schema['sourceLayer'] ?? 'unknown';
            $file = $schema['sourceFile'] ?? 'unknown';
            $layerLabel = static::LAYER_LABELS[$layer] ?? strtoupper($layer);

            $io->writeln(sprintf('  ✓ %s <comment>(%s)</comment>', $file, $layerLabel));
        }

        $io->newLine();

        return static::CODE_SUCCESS;
    }

    /**
     * @param array<string, mixed> $mergedSchema
     */
    protected function showMergedSchema(
        SymfonyStyle $io,
        string $resourceName,
        string $apiType,
        array $mergedSchema,
        string $validationStatus
    ): int {
        $io->title(sprintf('Merged Schema for Resource: %s (%s)', $resourceName, $apiType));

        $io->writeln(sprintf('Validation Status: %s', $validationStatus));
        $io->newLine();

        $cleanedSchema = $this->cleanSchemaForDisplay($mergedSchema);

        $yamlOutput = Yaml::dump($cleanedSchema, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $io->writeln($yamlOutput);

        return static::CODE_SUCCESS;
    }

    /**
     * @param array<array<string, mixed>> $schemas
     * @param array<string, mixed> $mergedSchema
     */
    protected function showResourceDetails(
        SymfonyStyle $io,
        string $resourceName,
        string $apiType,
        array $schemas,
        array $mergedSchema,
        string $validationStatus
    ): int {
        $io->title(sprintf('Resource: %s (%s)', $resourceName, $apiType));

        $io->section('Source Files (priority order):');

        $sortedSchemas = $this->sortSchemasByLayer($schemas);

        foreach ($sortedSchemas as $schema) {
            $layer = $schema['sourceLayer'] ?? 'unknown';
            $file = $schema['sourceFile'] ?? 'unknown';
            $layerLabel = static::LAYER_LABELS[$layer] ?? strtoupper($layer);

            $io->writeln(sprintf('  ✓ %s <comment>(%s)</comment>', $file, $layerLabel));
        }

        $io->newLine();

        $io->writeln(sprintf('Generated Class: <comment>%s%sResource.php</comment>', $resourceName, ApiTypeNormalizer::normalizeForGeneration($apiType)));
        $io->writeln(sprintf('Validation Status: %s', $validationStatus));

        $io->newLine();

        $properties = $mergedSchema['properties'] ?? [];
        $operations = $mergedSchema['operations'] ?? [];

        $io->section(sprintf('Properties: %d', count($properties)));

        $propertyRows = [];

        foreach (array_slice($properties, 0, 10) as $propertyName => $property) {
            $type = $property['type'] ?? 'unknown';
            $attributes = [];

            if (isset($property['identifier']) && $property['identifier']) {
                $attributes[] = 'identifier';
            }

            if (isset($property['required']) && $property['required']) {
                $attributes[] = 'required';
            }

            $attributesStr = $attributes !== [] ? ', ' . implode(', ', $attributes) : '';

            $propertyRows[] = [sprintf('  - %s', $propertyName), sprintf('(%s%s)', $type, $attributesStr)];
        }

        if (count($properties) > 10) {
            $propertyRows[] = ['  ...', sprintf('(%d more)', count($properties) - 10)];
        }

        if ($propertyRows !== []) {
            $io->table(['Property', 'Type'], $propertyRows);
        }

        $io->section(sprintf('Operations: %d', count($operations)));

        if ($operations !== []) {
            foreach ($operations as $operation) {
                $io->writeln(sprintf('  - %s', $operation['type']));
            }
        }

        $io->newLine();

        return static::CODE_SUCCESS;
    }

    /**
     * @return array<string>
     */
    protected function discoverApiTypes(): array
    {
        $configuredApiTypes = $this->config->getApiTypes();

        if ($configuredApiTypes !== []) {
            return $configuredApiTypes;
        }

        $apiTypes = [];

        foreach ($this->config->getSourceDirectories() as $sourceDir) {
            $apiResourcePath = sprintf('%s/*/resources/api', $sourceDir);
            $matches = glob($apiResourcePath, GLOB_ONLYDIR);

            if ($matches === false) {
                continue;
            }

            foreach ($matches as $match) {
                $subDirs = glob(sprintf('%s/*', $match), GLOB_ONLYDIR);

                if ($subDirs === false) {
                    continue;
                }

                foreach ($subDirs as $subDir) {
                    $apiTypes[] = basename($subDir);
                }
            }
        }

        return array_unique($apiTypes);
    }

    /**
     * @return array<string, int>
     */
    protected function discoverResourcesForApiType(string $apiType): array
    {
        $resources = [];

        foreach ($this->schemaFinder->findSchemaFiles($apiType) as $file) {
            $schema = $this->loadSchema($file);
            $parsedSchema = $this->schemaParser->parse($schema, $file);
            $resourceName = $parsedSchema['name'] ?? 'unknown';

            if (!isset($resources[$resourceName])) {
                $resources[$resourceName] = 0;
            }

            $resources[$resourceName]++;
        }

        return $resources;
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function findSchemasForResource(string $resourceName, string $apiType): array
    {
        $schemas = [];

        foreach ($this->schemaFinder->findSchemaFiles($apiType) as $file) {
            $schema = $this->loadSchema($file);
            $parsedSchema = $this->schemaParser->parse($schema, $file);

            if (($parsedSchema['name'] ?? '') === $resourceName) {
                $schemas[] = $parsedSchema;
            }
        }

        return $schemas;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadSchema(SplFileInfo $file): array
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($file)) {
                return $loader->load($file);
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $mergedSchema
     */
    protected function getValidationStatus(array $mergedSchema): string
    {
        try {
            $this->schemaValidator->validatePostMerge($mergedSchema);

            return '<info>✓ Valid</info>';
        } catch (Throwable $exception) {
            return sprintf('<error>✗ Invalid: %s</error>', $exception->getMessage());
        }
    }

    /**
     * @param array<array<string, mixed>> $schemas
     *
     * @return array<array<string, mixed>>
     */
    protected function sortSchemasByLayer(array $schemas): array
    {
        $layerPriority = ['core' => 1, 'feature' => 2, 'project' => 3];

        usort($schemas, static function (array $a, array $b) use ($layerPriority): int {
            $layerA = $a['sourceLayer'] ?? 'unknown';
            $layerB = $b['sourceLayer'] ?? 'unknown';

            $priorityA = $layerPriority[$layerA] ?? 999;
            $priorityB = $layerPriority[$layerB] ?? 999;

            return $priorityA <=> $priorityB;
        });

        return $schemas;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    protected function cleanSchemaForDisplay(array $schema): array
    {
        return array_filter(
            $schema,
            static fn (string $key): bool => !in_array($key, ['sourceFile', 'sourceLayer', 'sourcePriority', 'contributingSources'], true),
            ARRAY_FILTER_USE_KEY,
        );
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
}
