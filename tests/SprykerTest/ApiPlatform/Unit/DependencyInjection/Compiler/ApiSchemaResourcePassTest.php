<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection\Compiler;

use Codeception\Test\Unit;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spryker\ApiPlatform\DependencyInjection\Compiler\ApiSchemaResourcePass;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group Compiler
 * @group ApiSchemaResourcePassTest
 * Add your own group annotations below this line
 */
class ApiSchemaResourcePassTest extends Unit
{
    protected const string SOURCE_DIRECTORIES_PARAMETER = 'spryker_api_platform.source_directories';

    protected const string API_TYPES_PARAMETER = 'spryker_api_platform.api_types';

    protected const string CANONICAL_OBJECT_SEARCH_DIRECTORIES_PARAMETER = 'spryker_api_platform.canonical_object_search_directories';

    protected const string STOREFRONT_SCHEMA_DIRECTORY = 'Alpha/resources/api/storefront';

    protected const int PAST_MODIFICATION_TIME = 1000000000;

    protected const int CONTAINER_BUILD_TIME = 1000000100;

    protected ?string $sourceDirectory = null;

    protected function _after(): void
    {
        if ($this->sourceDirectory !== null) {
            exec(sprintf('rm -rf %s', escapeshellarg($this->sourceDirectory)));
            $this->sourceDirectory = null;
        }
    }

    public function testGivenSchemaDirectoriesOfTwoApiTypesWhenProcessingThenRegistersEachAsDirectoryResource(): void
    {
        // Arrange
        $sourceDirectory = $this->createSchemaDirectories([static::STOREFRONT_SCHEMA_DIRECTORY, 'Beta/resources/api/backend']);
        $container = $this->createContainer([$sourceDirectory], ['Storefront', 'Backend']);

        // Act
        (new ApiSchemaResourcePass())->process($container);

        // Assert
        $this->assertSame(
            [
                sprintf('%s/%s', $sourceDirectory, static::STOREFRONT_SCHEMA_DIRECTORY),
                sprintf('%s/Beta/resources/api/backend', $sourceDirectory),
            ],
            $this->getDirectoryResourcePaths($container),
        );
    }

    public function testGivenCanonicalObjectSearchDirectoriesWhenProcessingThenRegistersTheExistingOnesAsDirectoryResource(): void
    {
        // Arrange
        $sourceDirectory = $this->createSchemaDirectories([static::STOREFRONT_SCHEMA_DIRECTORY, 'objects/storefront']);
        $container = $this->createContainer(
            [$sourceDirectory],
            ['Storefront'],
            ['storefront' => [sprintf('%s/objects/storefront', $sourceDirectory), sprintf('%s/missing', $sourceDirectory)]],
        );

        // Act
        (new ApiSchemaResourcePass())->process($container);

        // Assert
        $this->assertSame(
            [
                sprintf('%s/%s', $sourceDirectory, static::STOREFRONT_SCHEMA_DIRECTORY),
                sprintf('%s/objects/storefront', $sourceDirectory),
            ],
            $this->getDirectoryResourcePaths($container),
        );
    }

    public function testGivenMissingCanonicalObjectSearchDirectoryWhenItIsCreatedThenTheContainerIsStale(): void
    {
        // Arrange
        $sourceDirectory = $this->createSchemaDirectories([static::STOREFRONT_SCHEMA_DIRECTORY]);
        $missingDirectory = sprintf('%s/objects/storefront', $sourceDirectory);
        $container = $this->createContainer([$sourceDirectory], ['Storefront'], ['storefront' => [$missingDirectory]]);
        (new ApiSchemaResourcePass())->process($container);
        $resources = $this->getResourcesOfType($this->dumpAndLoadResources($container), FileExistenceResource::class);

        // Act
        mkdir($missingDirectory, 0777, true);

        // Assert
        $this->assertCount(1, $resources);
        $this->assertFalse($resources[0]->isFresh(static::CONTAINER_BUILD_TIME));
    }

    public function testGivenSchemaFileWhenItChangesThenTheDirectoryResourceIsStale(): void
    {
        // Arrange
        $schemaFile = $this->createBackdatedSchemaFile('objects/totals.object.yml');
        $resource = $this->processAndGetSchemaDirectoryResource();

        // Act
        touch($schemaFile, static::CONTAINER_BUILD_TIME + 5);
        clearstatcache();

        // Assert
        $this->assertFalse($resource->isFresh(static::CONTAINER_BUILD_TIME));
    }

    public function testGivenNonSchemaFileWhenItChangesThenTheDirectoryResourceStaysFresh(): void
    {
        // Arrange
        $this->createBackdatedSchemaFile('carts.resource.yml');
        $readmeFile = $this->createBackdatedSchemaFile('README.md');
        $resource = $this->processAndGetSchemaDirectoryResource();

        // Act
        touch($readmeFile, static::CONTAINER_BUILD_TIME + 5);
        clearstatcache();

        // Assert
        $this->assertTrue($resource->isFresh(static::CONTAINER_BUILD_TIME));
    }

    public function testGivenSchemaFileWhenItIsAddedThenTheDirectoryResourceIsStale(): void
    {
        // Arrange
        $this->createBackdatedSchemaFile('carts.resource.yml');
        $resource = $this->processAndGetSchemaDirectoryResource();

        // Act
        file_put_contents(sprintf('%s/%s/orders.resource.yml', $this->sourceDirectory, static::STOREFRONT_SCHEMA_DIRECTORY), 'resource: {}');
        clearstatcache();

        // Assert
        $this->assertFalse($resource->isFresh(static::CONTAINER_BUILD_TIME));
    }

    public function testGivenNewModuleSchemaDirectoryWhenItIsCreatedThenAGlobResourceIsStale(): void
    {
        // Arrange
        $sourceDirectory = $this->createSchemaDirectories([static::STOREFRONT_SCHEMA_DIRECTORY]);
        $container = $this->createContainer([$sourceDirectory], ['Storefront']);
        (new ApiSchemaResourcePass())->process($container);
        $globResources = $this->getResourcesOfType($this->dumpAndLoadResources($container), GlobResource::class);

        // Act
        $this->createSchemaDirectories(['Beta/resources/api/storefront']);
        file_put_contents(sprintf('%s/Beta/resources/api/storefront/orders.resource.yml', $sourceDirectory), 'resource: {}');

        // Assert
        $staleGlobResources = array_filter(
            $globResources,
            static fn (GlobResource $globResource): bool => !$globResource->isFresh(static::CONTAINER_BUILD_TIME),
        );
        $this->assertNotSame([], $staleGlobResources);
    }

    public function testGivenNoSourceDirectoriesParameterWhenProcessingThenAddsNoResource(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter(static::API_TYPES_PARAMETER, ['Storefront']);

        // Act
        (new ApiSchemaResourcePass())->process($container);

        // Assert
        $this->assertSame([], $container->getResources());
    }

    protected function createBackdatedSchemaFile(string $relativePath): string
    {
        $schemaFile = sprintf('%s/%s/%s', $this->createSchemaDirectories([static::STOREFRONT_SCHEMA_DIRECTORY]), static::STOREFRONT_SCHEMA_DIRECTORY, $relativePath);

        if (!is_dir(dirname($schemaFile))) {
            mkdir(dirname($schemaFile), 0777, true);
        }

        file_put_contents($schemaFile, 'resource: {}');
        $this->setPastModificationTime((string)$this->sourceDirectory);

        return $schemaFile;
    }

    protected function processAndGetSchemaDirectoryResource(): DirectoryResource
    {
        $container = $this->createContainer([(string)$this->sourceDirectory], ['Storefront']);
        (new ApiSchemaResourcePass())->process($container);
        $directoryResources = $this->getResourcesOfType($container->getResources(), DirectoryResource::class);
        $this->assertCount(1, $directoryResources);

        return $directoryResources[0];
    }

    /**
     * @param array<string> $sourceDirectories
     * @param array<string> $apiTypes
     * @param array<string, array<string>> $canonicalObjectSearchDirectories
     */
    protected function createContainer(array $sourceDirectories, array $apiTypes, array $canonicalObjectSearchDirectories = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(static::SOURCE_DIRECTORIES_PARAMETER, $sourceDirectories);
        $container->setParameter(static::API_TYPES_PARAMETER, $apiTypes);
        $container->setParameter(static::CANONICAL_OBJECT_SEARCH_DIRECTORIES_PARAMETER, $canonicalObjectSearchDirectories);

        return $container;
    }

    /**
     * @param array<string> $relativeDirectories
     */
    protected function createSchemaDirectories(array $relativeDirectories): string
    {
        $this->sourceDirectory ??= sprintf('%s/api-schema-resource-pass-%s', sys_get_temp_dir(), uniqid());

        foreach ($relativeDirectories as $relativeDirectory) {
            $directory = sprintf('%s/%s', $this->sourceDirectory, $relativeDirectory);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        return $this->sourceDirectory;
    }

    protected function setPastModificationTime(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            touch($fileInfo->getPathname(), static::PAST_MODIFICATION_TIME);
        }

        touch($directory, static::PAST_MODIFICATION_TIME);
        clearstatcache();
    }

    /**
     * @return array<\Symfony\Component\Config\Resource\ResourceInterface>
     */
    protected function dumpAndLoadResources(ContainerBuilder $container): array
    {
        return unserialize(serialize($container->getResources()));
    }

    /**
     * @template T of \Symfony\Component\Config\Resource\ResourceInterface
     *
     * @param array<\Symfony\Component\Config\Resource\ResourceInterface> $resources
     * @param class-string<T> $resourceClass
     *
     * @return array<T>
     */
    protected function getResourcesOfType(array $resources, string $resourceClass): array
    {
        return array_values(array_filter(
            $resources,
            static fn (ResourceInterface $resource): bool => $resource instanceof $resourceClass,
        ));
    }

    /**
     * @return array<string>
     */
    protected function getDirectoryResourcePaths(ContainerBuilder $container): array
    {
        $paths = array_map(
            static fn (DirectoryResource $resource): string => $resource->getResource(),
            $this->getResourcesOfType($container->getResources(), DirectoryResource::class),
        );
        sort($paths);

        return $paths;
    }
}
