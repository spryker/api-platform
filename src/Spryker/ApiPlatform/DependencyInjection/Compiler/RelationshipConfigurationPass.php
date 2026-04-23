<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use SplFileInfo;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolver;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Builds the api_platform.relationships container parameter from resource schema files.
 *
 * This compiler pass scans all resource schema files (*.resource.yml/yaml) and extracts
 * the 'includes' configuration to build a centralized relationship registry stored as
 * the 'api_platform.relationships' container parameter.
 *
 * Supports both singular (`resource:`) and plural (`resources:`) YAML formats.
 * Resolves cross-module target resources via a global provider index.
 * Supports `resolverClass` for complex relationships requiring custom logic.
 */
class RelationshipConfigurationPass implements CompilerPassInterface
{
    /**
     * Global index mapping resource identifiers to their provider and shortName.
     *
     * @var array<string, array{provider: string, shortName: string}>
     */
    protected array $providerIndex = [];

    public function __construct(
        protected SchemaFileDiscovery $schemaFileDiscovery = new SchemaFileDiscovery(),
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$this->hasRequiredParameters($container)) {
            return;
        }

        $apiTypes = $container->getParameter('spryker_api_platform.api_types');

        if (!is_array($apiTypes) || $apiTypes === []) {
            return;
        }

        $sourceDirectories = $container->getParameter('spryker_api_platform.source_directories');

        if (!is_array($sourceDirectories)) {
            return;
        }

        $relationships = [];

        foreach ($apiTypes as $apiType) {
            $schemaFiles = $this->schemaFileDiscovery->findSchemaFiles($sourceDirectories, $apiType);

            $this->buildProviderIndex($schemaFiles);

            foreach ($schemaFiles as $schemaFile) {
                $relationshipConfigs = $this->extractRelationshipsFromSchema($schemaFile);

                $relationships = array_merge($relationships, $relationshipConfigs);
            }

            $this->providerIndex = [];
        }

        $container->setParameter('api_platform.relationships', $relationships);

        $this->registerResolverServices($container, $relationships);
        $this->replaceWithScopedLocators($container, $relationships);
    }

    protected function hasRequiredParameters(ContainerBuilder $container): bool
    {
        return $container->hasParameter('spryker_api_platform.api_types')
            && $container->hasParameter('spryker_api_platform.source_directories');
    }

    /**
     * Builds a global index of all resource providers across all schema files.
     * This enables cross-module target resource resolution.
     *
     * @param array<\SplFileInfo> $schemaFiles
     *
     * @return void
     */
    protected function buildProviderIndex(array $schemaFiles): void
    {
        $this->providerIndex = [];

        foreach ($schemaFiles as $schemaFile) {
            $schema = $this->schemaFileDiscovery->parseSchemaFile($schemaFile);

            if ($schema === null) {
                continue;
            }

            $resourceDefinitions = $this->schemaFileDiscovery->extractResourceDefinitions($schema);

            foreach ($resourceDefinitions as $resourceDefinition) {
                $this->indexResourceProvider($resourceDefinition);
            }
        }
    }

    /**
     * Indexes a resource definition by all available identifiers (name, resource key, shortName).
     *
     * @param array<string, mixed> $resource
     *
     * @return void
     */
    protected function indexResourceProvider(array $resource): void
    {
        $provider = $resource['provider'] ?? null;

        if ($provider === null || !is_string($provider)) {
            return;
        }

        $name = $resource['name'] ?? null;
        $resourceKey = $resource['resource'] ?? null;
        $shortName = $resource['shortName'] ?? null;
        $resolvedShortName = $shortName ?? $name ?? $resourceKey ?? '';

        $indexEntry = [
            'provider' => $provider,
            'shortName' => is_string($resolvedShortName) ? $resolvedShortName : '',
        ];

        if (is_string($name)) {
            $this->providerIndex[$name] = $indexEntry;
        }

        if (is_string($resourceKey)) {
            $this->providerIndex[$resourceKey] = $indexEntry;
        }

        if (is_string($shortName)) {
            $this->providerIndex[$shortName] = $indexEntry;
        }
    }

    /**
     * @param \SplFileInfo $schemaFile
     *
     * @return array<string, array<string, mixed>>
     */
    protected function extractRelationshipsFromSchema(SplFileInfo $schemaFile): array
    {
        $schema = $this->schemaFileDiscovery->parseSchemaFile($schemaFile);

        if ($schema === null) {
            return [];
        }

        $resourceDefinitions = $this->schemaFileDiscovery->extractResourceDefinitions($schema);
        $relationships = [];

        foreach ($resourceDefinitions as $resource) {
            $resourceShortName = $resource['shortName'] ?? $resource['name'] ?? null;

            if ($resourceShortName === null) {
                continue;
            }

            $includes = $resource['includes'] ?? [];

            if (!is_array($includes) || $includes === []) {
                continue;
            }

            foreach ($includes as $include) {
                if (!is_array($include)) {
                    continue;
                }

                $relationshipConfig = $this->buildRelationshipConfig($include, $resourceShortName);

                if ($relationshipConfig === null) {
                    continue;
                }

                $relationships[$relationshipConfig['key']] = $relationshipConfig['config'];
            }
        }

        return $relationships;
    }

    /**
     * Builds a single relationship configuration entry from an include definition.
     *
     * Supports two modes:
     * - Provider-based: uses targetResource to find a provider via the global index
     * - Resolver-based: uses resolverClass for complex relationships requiring custom logic
     *
     * @param array<string, mixed> $include
     * @param string $resourceShortName
     *
     * @return array{key: string, config: array<string, mixed>}|null
     */
    protected function buildRelationshipConfig(array $include, string $resourceShortName): ?array
    {
        $relationshipName = $include['relationshipName'] ?? null;

        if ($relationshipName === null) {
            return null;
        }

        $resolverClass = $include['resolverClass'] ?? null;

        if (is_string($resolverClass)) {
            return $this->buildResolverRelationshipConfig($include, $resourceShortName, $resolverClass);
        }

        return $this->buildProviderRelationshipConfig($include, $resourceShortName);
    }

    /**
     * @param array<string, mixed> $include
     * @param string $resourceShortName
     * @param string $resolverClass
     *
     * @return array{key: string, config: array<string, mixed>}
     */
    protected function buildResolverRelationshipConfig(
        array $include,
        string $resourceShortName,
        string $resolverClass,
    ): array {
        $relationshipName = $include['relationshipName'];
        $targetResource = $include['targetResource'] ?? null;
        $targetResourceType = $relationshipName;

        if (is_string($targetResource)) {
            $targetEntry = $this->providerIndex[$targetResource] ?? null;
            $targetResourceType = $targetEntry !== null ? $targetEntry['shortName'] : $relationshipName;
        }

        $key = sprintf('%s.%s', $resourceShortName, $relationshipName);

        return [
            'key' => $key,
            'config' => [
                'relationship_name' => $relationshipName,
                'target_resource_type' => $targetResourceType,
                'resolver_class' => $resolverClass,
                'uri_variable_mappings' => [],
                'auto_include' => (bool)($include['autoInclude'] ?? false),
                'auto_include_max_depth' => (int)($include['autoIncludeMaxDepth'] ?? PHP_INT_MAX),
                'auto_include_min_depth' => (int)($include['autoIncludeMinDepth'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $include
     * @param string $resourceShortName
     *
     * @return array{key: string, config: array<string, mixed>}|null
     */
    protected function buildProviderRelationshipConfig(array $include, string $resourceShortName): ?array
    {
        $relationshipName = $include['relationshipName'];
        $targetResource = $include['targetResource'] ?? null;

        if ($targetResource === null) {
            return null;
        }

        $targetEntry = $this->findTargetResourceEntry($targetResource);

        if ($targetEntry === null) {
            return null;
        }

        $uriVariableMappings = $include['uriVariableMappings'] ?? [];

        if (!is_array($uriVariableMappings)) {
            $uriVariableMappings = [];
        }

        $key = sprintf('%s.%s', $resourceShortName, $relationshipName);

        return [
            'key' => $key,
            'config' => [
                'relationship_name' => $relationshipName,
                'target_resource_type' => $targetEntry['shortName'],
                'provider_service_id' => $targetEntry['provider'],
                'uri_variable_mappings' => $uriVariableMappings,
                'auto_include' => (bool)($include['autoInclude'] ?? false),
                'auto_include_max_depth' => (int)($include['autoIncludeMaxDepth'] ?? PHP_INT_MAX),
                'auto_include_min_depth' => (int)($include['autoIncludeMinDepth'] ?? 0),
            ],
        ];
    }

    /**
     * Finds a target resource entry from the global provider index.
     *
     * @param string $targetResource
     *
     * @return array{provider: string, shortName: string}|null
     */
    protected function findTargetResourceEntry(string $targetResource): ?array
    {
        return $this->providerIndex[$targetResource] ?? null;
    }

    /**
     * Registers resolver classes as public autowired services in the container.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @param array<string, array<string, mixed>> $relationships
     *
     * @return void
     */
    protected function registerResolverServices(ContainerBuilder $container, array $relationships): void
    {
        foreach ($relationships as $config) {
            $resolverClass = $config['resolver_class'] ?? null;

            if (!is_string($resolverClass) || $container->has($resolverClass)) {
                continue;
            }

            if (!class_exists($resolverClass)) {
                continue;
            }

            $definition = new Definition($resolverClass);
            $definition->setPublic(true);
            $definition->setAutowired(true);
            $definition->setAutoconfigured(true);

            $container->setDefinition($resolverClass, $definition);
        }
    }

    /**
     * Replaces the full service container injection on ApiPlatformRelationshipResolver
     * with scoped ServiceLocator instances containing only the provider and resolver
     * services that are actually referenced by the relationship configuration.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @param array<string, array<string, mixed>> $relationships
     *
     * @return void
     */
    protected function replaceWithScopedLocators(ContainerBuilder $container, array $relationships): void
    {
        if (!$container->hasDefinition(ApiPlatformRelationshipResolver::class)) {
            return;
        }

        $providerReferences = [];
        $resolverReferences = [];

        foreach ($relationships as $config) {
            $providerServiceId = $config['provider_service_id'] ?? null;

            if (is_string($providerServiceId)) {
                $providerReferences[$providerServiceId] = new Reference($providerServiceId);
            }

            $resolverClass = $config['resolver_class'] ?? null;

            if (is_string($resolverClass)) {
                $resolverReferences[$resolverClass] = new Reference($resolverClass);
            }
        }

        $definition = $container->getDefinition(ApiPlatformRelationshipResolver::class);
        $definition->setArgument('$providerLocator', new ServiceLocatorArgument($providerReferences));
        $definition->setArgument('$resolverLocator', new ServiceLocatorArgument($resolverReferences));
    }
}
