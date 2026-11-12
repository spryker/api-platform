<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Command;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Spryker\ApiPlatform\Exception\InvalidOwnershipDeclarationException;
use Spryker\ApiPlatform\Security\Permission\OperationPermissionIdentifierResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PermissionRegistryDumpCommand extends Command
{
    protected const string NAME = 'api:permission-registry:dump';

    protected const string OPTION_OUTPUT = 'output';

    /**
     * Kept outside `<generated_dir>/<ApiType>/`, which the resource generator wipes on every run, including `cache:warmup`.
     */
    protected const string REGISTRY_DIRECTORY_NAME = 'PermissionRegistry';

    protected const string OWNERSHIP_OWNER_URI_VARIABLE = 'ownerUriVariable';

    protected const string OWNERSHIP_OBJECT_TYPE = 'objectType';

    protected const string OWNERSHIP_OBJECT_URI_VARIABLE = 'objectUriVariable';

    protected const string OWNERSHIP_COLLECTION = 'collection';

    /**
     * @var list<string>
     */
    protected const array OWNERSHIP_DENIAL_KEYS = ['denial', 'sharedReadOnlyDenial'];

    /**
     * @param list<string> $apiTypes
     */
    public function __construct(
        protected readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        protected readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        protected readonly array $apiTypes,
        protected readonly string $generatedDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(static::NAME)
            ->setDescription('Writes every API operation with its permission identifier and baseline security expression to a JSON registry.')
            ->addOption(
                static::OPTION_OUTPUT,
                null,
                InputOption::VALUE_REQUIRED,
                'File to write; defaults to <generated_dir>/PermissionRegistry/<ApiType>.json.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiType = $this->apiTypes[0] ?? null;

        if ($apiType === null) {
            $output->writeln('<error>No API type configured.</error>');

            return static::FAILURE;
        }

        try {
            [$entries, $duplicates] = $this->collectRegistry($apiType);
        } catch (InvalidOwnershipDeclarationException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return static::FAILURE;
        }

        if ($duplicates !== []) {
            foreach ($duplicates as $identifier => $endpoints) {
                $output->writeln(sprintf('<error>Permission identifier %s is shared by different endpoints: %s.</error>', $identifier, implode(', ', $endpoints)));
            }

            return static::FAILURE;
        }

        $file = $input->getOption(static::OPTION_OUTPUT)
            ?? sprintf('%s/%s/%s.json', rtrim($this->generatedDirectory, '/'), static::REGISTRY_DIRECTORY_NAME, ucfirst($apiType));
        $directory = dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $output->writeln(sprintf('<error>Could not create directory %s.</error>', $directory));

            return static::FAILURE;
        }

        if (file_put_contents($file, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL) === false) {
            $output->writeln(sprintf('<error>Could not write %s.</error>', $file));

            return static::FAILURE;
        }

        $output->writeln(sprintf('Wrote %d operations to %s', count($entries), $file));

        return static::SUCCESS;
    }

    /**
     * An identifier must name one endpoint; the same method and URI template in two resource classes is one endpoint.
     *
     * @return array{0: list<array{identifier: string, api_type: string, short_name: string, operation_name: string, method: string|null, uri_template: string, baseline_security: string|null, ownership_owner_uri_variable: string|null}>, 1: array<string, list<string>>}
     */
    protected function collectRegistry(string $apiType): array
    {
        $entries = [];
        $endpointsByIdentifier = [];

        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $apiResource) {
                foreach ($this->collectResourceEntries($apiType, $apiResource) as $identifier => $entry) {
                    $endpoint = sprintf('%s %s', $entry['method'], str_replace('{._format}', '', $entry['uri_template']));
                    $endpointsByIdentifier[$identifier][$endpoint] = $endpoint;
                    $entries[$identifier] = $entry;
                }
            }
        }

        $duplicates = [];

        foreach ($endpointsByIdentifier as $identifier => $endpoints) {
            if (count($endpoints) > 1) {
                $duplicates[$identifier] = array_values($endpoints);
            }
        }

        ksort($entries);
        ksort($duplicates);

        return [array_values($entries), $duplicates];
    }

    /**
     * @return array<string, array{identifier: string, api_type: string, short_name: string, operation_name: string, method: string|null, uri_template: string, baseline_security: string|null, ownership_owner_uri_variable: string|null}>
     */
    protected function collectResourceEntries(string $apiType, ApiResource $apiResource): array
    {
        $entries = [];

        foreach ($apiResource->getOperations() ?? [] as $operationName => $operation) {
            $extraProperties = $operation->getExtraProperties() ?? [];
            $identifier = $extraProperties[OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER] ?? null;

            if ($identifier === null) {
                continue;
            }

            $ownership = (array)($extraProperties[OperationPermissionIdentifierResolver::EXTRA_PROPERTY_OWNERSHIP] ?? []);
            $this->assertOwnershipDeclaration($identifier, $ownership);

            $entries[$identifier] = [
                'identifier' => $identifier,
                'api_type' => $apiType,
                'short_name' => (string)$operation->getShortName(),
                'operation_name' => (string)$operationName,
                'method' => $operation->getMethod(),
                'uri_template' => (string)$operation->getUriTemplate(),
                'baseline_security' => $extraProperties[OperationPermissionIdentifierResolver::EXTRA_PROPERTY_BASELINE_SECURITY] ?? null,
                'ownership_owner_uri_variable' => $ownership[static::OWNERSHIP_OWNER_URI_VARIABLE] ?? null,
            ];
        }

        return $entries;
    }

    /**
     * @param array<mixed> $ownership
     *
     * @throws \Spryker\ApiPlatform\Exception\InvalidOwnershipDeclarationException
     */
    protected function assertOwnershipDeclaration(string $identifier, array $ownership): void
    {
        if ($ownership === []) {
            return;
        }

        $error = $this->findOwnershipDeclarationError($ownership);

        if ($error !== null) {
            throw new InvalidOwnershipDeclarationException(sprintf('The securityOwnership declaration of %s is malformed: %s.', $identifier, $error));
        }
    }

    /**
     * @param array<mixed> $ownership
     */
    protected function findOwnershipDeclarationError(array $ownership): ?string
    {
        $hasRouteOwner = array_key_exists(static::OWNERSHIP_OWNER_URI_VARIABLE, $ownership);
        $hasObject = array_key_exists(static::OWNERSHIP_OBJECT_TYPE, $ownership) || array_key_exists(static::OWNERSHIP_OBJECT_URI_VARIABLE, $ownership);
        $hasCollection = array_key_exists(static::OWNERSHIP_COLLECTION, $ownership);

        if ((int)$hasRouteOwner + (int)$hasObject + (int)$hasCollection !== 1) {
            return 'it needs exactly one of ownerUriVariable, objectType with objectUriVariable, or collection';
        }

        if ($hasRouteOwner && !$this->isNonEmptyString($ownership[static::OWNERSHIP_OWNER_URI_VARIABLE])) {
            return 'ownerUriVariable must be a non-empty string';
        }

        if ($hasObject && !($this->isNonEmptyString($ownership[static::OWNERSHIP_OBJECT_TYPE] ?? null) && $this->isNonEmptyString($ownership[static::OWNERSHIP_OBJECT_URI_VARIABLE] ?? null))) {
            return 'objectType and objectUriVariable must both be non-empty strings';
        }

        if ($hasCollection && $ownership[static::OWNERSHIP_COLLECTION] !== true) {
            return 'collection must be true';
        }

        foreach (static::OWNERSHIP_DENIAL_KEYS as $denialKey) {
            if (array_key_exists($denialKey, $ownership) && !$this->isDenial($ownership[$denialKey])) {
                return sprintf('%s needs status (int), code (string) and detail (string)', $denialKey);
            }
        }

        return null;
    }

    protected function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    protected function isDenial(mixed $denial): bool
    {
        return is_array($denial)
            && is_int($denial['status'] ?? null)
            && is_string($denial['code'] ?? null)
            && is_string($denial['detail'] ?? null);
    }
}
