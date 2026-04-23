<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Relationship;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use BadMethodCallException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionProperty;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\ApiPlatform\Provider\BatchLoadableProviderInterface;
use Throwable;

class ApiPlatformRelationshipResolver implements ApiPlatformRelationshipResolverInterface
{
    protected const int MAX_NESTING_DEPTH = 3;

    /**
     * @var array<string, array<object>>
     */
    protected array $relationshipCache = [];

    /**
     * @var array<string, array<string, array<object>>>
     */
    protected array $perItemRelationshipData = [];

    /**
     * @param array<string, array<string, mixed>> $relationships
     * @param \Psr\Container\ContainerInterface $providerLocator
     * @param \Psr\Container\ContainerInterface $resolverLocator
     */
    public function __construct(
        protected array $relationships,
        protected ContainerInterface $providerLocator,
        protected ContainerInterface $resolverLocator,
    ) {
    }

    /**
     * @param string $mainResourceType
     * @param array<object> $mainResources
     * @param array<string> $requestedIncludes
     * @param array<string, mixed> $context
     *
     * @return array<string, array<object>>
     */
    public function resolveRelationships(
        string $mainResourceType,
        array $mainResources,
        array $requestedIncludes,
        array $context,
    ): array {
        $relationships = [];
        $processedIncludes = [];
        $resourceTypesByPath = [];
        $resolvedResourceTypes = [$mainResourceType => true];

        foreach ($requestedIncludes as $includeName) {
            $depth = substr_count($includeName, '.');

            if ($depth >= static::MAX_NESTING_DEPTH) {
                continue;
            }

            $resolvedRelationships = $this->resolveNestedRelationship(
                $mainResourceType,
                $mainResources,
                $includeName,
                $context,
                $processedIncludes,
                $resourceTypesByPath,
            );

            $relationships = array_merge($relationships, $resolvedRelationships);
        }

        // Second pass: resolve flat includes that weren't found as direct relationships
        // by auto-nesting them under a resolved parent that has the relationship configured.
        // This supports backward-compatible flat includes like ?include=items,concrete-products
        // where concrete-products is a child of items, not the main resource.
        $relationships = $this->resolveOrphanedFlatIncludes(
            $mainResourceType,
            $mainResources,
            $requestedIncludes,
            $relationships,
            $processedIncludes,
            $resourceTypesByPath,
            $context,
        );

        // Track all resource types resolved so far (used by autoIncludeChildRelationships)
        foreach ($resourceTypesByPath as $resourceType) {
            $resolvedResourceTypes[$resourceType] = true;
        }

        // Third pass: auto-include flagged child relationships and transitively resolve
        // requested includes for all resolved resources. The legacy Glue API automatically
        // included child relationships of included resources and resolved the user's
        // requested includes transitively through the include chain.
        $relationships = $this->autoIncludeChildRelationships(
            $requestedIncludes,
            $relationships,
            $processedIncludes,
            $resourceTypesByPath,
            $resolvedResourceTypes,
            $context,
        );

        return $relationships;
    }

    /**
     * @return array<string, array<string, array<object>>>
     */
    public function getPerItemRelationshipData(): array
    {
        return $this->perItemRelationshipData;
    }

    /**
     * Resolves flat includes that were not found as direct relationships of the main resource.
     *
     * @param array<object> $mainResources
     * @param array<string> $requestedIncludes
     * @param array<string, array<object>> $relationships
     * @param array<string, array<object>> $processedIncludes
     * @param array<string, mixed> $resourceTypesByPath
     * @param array<string, mixed> $context
     *
     * @return array<string, array<object>>
     */
    protected function resolveOrphanedFlatIncludes(
        string $mainResourceType,
        array $mainResources,
        array $requestedIncludes,
        array $relationships,
        array &$processedIncludes,
        array &$resourceTypesByPath,
        array $context,
    ): array {
        foreach ($requestedIncludes as $includeName) {
            if (str_contains($includeName, '.') || isset($relationships[$includeName])) {
                continue;
            }

            $nestedPath = $this->findParentPathForOrphanedInclude($includeName, $resourceTypesByPath);

            if ($nestedPath === null) {
                continue;
            }

            $resolvedRelationships = $this->resolveNestedRelationship(
                $mainResourceType,
                $mainResources,
                $nestedPath,
                $context,
                $processedIncludes,
                $resourceTypesByPath,
            );

            $relationships = array_merge($relationships, $resolvedRelationships);
        }

        return $relationships;
    }

    /**
     * @param array<string, string> $resourceTypesByPath
     */
    protected function findParentPathForOrphanedInclude(string $includeName, array $resourceTypesByPath): ?string
    {
        foreach ($resourceTypesByPath as $parentPath => $parentResourceType) {
            if ($this->getRelationshipConfig($parentResourceType, $includeName) !== null) {
                return sprintf('%s.%s', $parentPath, $includeName);
            }
        }

        return null;
    }

    /**
     * Auto-includes flagged child relationships and transitively resolves the user's
     * requested includes for all resolved resources. This replicates the legacy Glue API
     * behavior where relationship plugins were executed for every included resource.
     *
     * @param array<string> $requestedIncludes
     * @param array<string, array<object>> $relationships
     * @param array<string, array<object>> $processedIncludes
     * @param array<string, mixed> $resourceTypesByPath
     * @param array<string, mixed> $context
     *
     * @return array<string, array<object>>
     */

    /**
     * @param array<string, bool> $resolvedResourceTypes
     */
    protected function autoIncludeChildRelationships(
        array $requestedIncludes,
        array $relationships,
        array &$processedIncludes,
        array &$resourceTypesByPath,
        array &$resolvedResourceTypes,
        array $context,
    ): array {
        $changed = true;

        while ($changed) {
            $changed = false;
            $pathsToProcess = $resourceTypesByPath;

            foreach ($pathsToProcess as $parentPath => $parentResourceType) {
                $currentDepth = substr_count($parentPath, '.');

                $childNames = array_unique(array_merge(
                    $this->findAutoIncludeChildNames($parentResourceType, $currentDepth),
                    $this->findRequestedChildNames($parentResourceType, $requestedIncludes),
                ));

                $resolvedChildren = $this->resolveChildRelationships(
                    $parentPath,
                    $parentResourceType,
                    $childNames,
                    $processedIncludes,
                    $context,
                );

                foreach ($resolvedChildren as $childPath => $childResult) {
                    $relationships[$childPath] = $childResult['resources'];
                    $processedIncludes[$childPath] = $childResult['resources'];
                    $resourceTypesByPath[$childPath] = $childResult['resourceType'];
                    $resolvedResourceTypes[$childResult['resourceType']] = true;
                    $changed = true;
                }
            }
        }

        return $relationships;
    }

    /**
     * @param array<string> $childNames
     * @param array<string, array<object>> $processedIncludes
     * @param array<string, mixed> $context
     *
     * @return array<string, array{resources: array<object>, resourceType: string}>
     */
    protected function resolveChildRelationships(
        string $parentPath,
        string $parentResourceType,
        array $childNames,
        array $processedIncludes,
        array $context,
    ): array {
        $resolved = [];

        foreach ($childNames as $childRelationshipName) {
            $childPath = sprintf('%s.%s', $parentPath, $childRelationshipName);

            if (substr_count($childPath, '.') > static::MAX_NESTING_DEPTH) {
                continue;
            }

            if (isset($processedIncludes[$childPath])) {
                continue;
            }

            $childConfig = $this->getRelationshipConfig($parentResourceType, $childRelationshipName);

            if ($childConfig === null) {
                continue;
            }

            $targetResourceType = $childConfig['target_resource_type'] ?? '';
            $parentResources = $processedIncludes[$parentPath] ?? [];

            if ($parentResources === []) {
                continue;
            }

            $childResources = $this->loadRelatedResources($parentResources, $childConfig, $context);

            if ($childResources === []) {
                continue;
            }

            $resolved[$childPath] = [
                'resources' => $childResources,
                'resourceType' => $targetResourceType,
            ];
        }

        return $resolved;
    }

    /**
     * Finds user-requested includes that are children of a given resource type.
     *
     * @param array<string> $requestedIncludes
     *
     * @return array<string>
     */
    protected function findRequestedChildNames(string $resourceType, array $requestedIncludes): array
    {
        $childNames = [];

        foreach ($requestedIncludes as $includeName) {
            if (str_contains($includeName, '.')) {
                continue;
            }

            if ($this->getRelationshipConfig($resourceType, $includeName) !== null) {
                $childNames[] = $includeName;
            }
        }

        return $childNames;
    }

    /**
     * Finds child relationship names that are marked for auto-inclusion.
     *
     * @return array<string>
     */
    protected function findAutoIncludeChildNames(string $resourceType, int $currentDepth): array
    {
        $childNames = [];
        $prefix = sprintf('%s.', $resourceType);

        foreach ($this->relationships as $key => $config) {
            if (!str_starts_with($key, $prefix)) {
                continue;
            }

            if (!($config['auto_include'] ?? false)) {
                continue;
            }

            $maxDepth = $config['auto_include_max_depth'] ?? static::MAX_NESTING_DEPTH;
            $minDepth = $config['auto_include_min_depth'] ?? 0;

            if ($currentDepth > $maxDepth || $currentDepth < $minDepth) {
                continue;
            }

            $childNames[] = substr($key, strlen($prefix));
        }

        return $childNames;
    }

    /**
     * @param string $mainResourceType
     * @param array<object> $mainResources
     * @param string $includePath
     * @param array<string, mixed> $context
     * @param array<string, array<object>> $processedIncludes
     * @param array<string, mixed> $resourceTypesByPath
     *
     * @return array<string, array<object>>
     */
    protected function resolveNestedRelationship(
        string $mainResourceType,
        array $mainResources,
        string $includePath,
        array $context,
        array &$processedIncludes,
        array &$resourceTypesByPath,
    ): array {
        $parts = explode('.', $includePath);
        $currentPath = '';
        $currentResources = $mainResources;
        $currentResourceType = $mainResourceType;
        $relationships = [];

        foreach ($parts as $index => $relationshipName) {
            $currentPath = $currentPath === '' ? $relationshipName : sprintf('%s.%s', $currentPath, $relationshipName);

            if (isset($processedIncludes[$currentPath])) {
                $currentResources = $processedIncludes[$currentPath];

                // Update currentResourceType so deeper nested paths resolve against the correct parent
                $currentResourceType = $resourceTypesByPath[$currentPath] ?? $currentResourceType;

                continue;
            }

            $relationshipConfig = $this->getRelationshipConfig($currentResourceType, $relationshipName);

            if (!$relationshipConfig) {
                break;
            }

            $relatedResources = $this->loadRelatedResources(
                $currentResources,
                $relationshipConfig,
                $context,
            );

            $targetResourceType = $relationshipConfig['target_resource_type'] ?? '';
            $relationships[$currentPath] = $relatedResources;
            $processedIncludes[$currentPath] = $relatedResources;
            $resourceTypesByPath[$currentPath] = $targetResourceType;
            $currentResources = $relatedResources;
            $currentResourceType = $targetResourceType;
        }

        return $relationships;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    public function parseIncludeParameter(array $context): array
    {
        $request = $context['request'] ?? null;

        if (!$request) {
            return [];
        }

        $includeParam = $request->query->get('include', '');

        if ($includeParam === '') {
            return [];
        }

        $includes = array_map('trim', explode(',', $includeParam));

        return $this->flattenNestedIncludes($includes);
    }

    /**
     * Flatten nested includes to include all intermediate relationships.
     *
     * Example: ['addresses.country'] becomes ['addresses', 'addresses.country']
     *
     * @param array<string> $includes
     *
     * @return array<string>
     */
    protected function flattenNestedIncludes(array $includes): array
    {
        $flattenedIncludes = [];

        foreach ($includes as $include) {
            $parts = explode('.', $include);
            $path = '';

            foreach ($parts as $part) {
                $path = $path === '' ? $part : sprintf('%s.%s', $path, $part);
                $flattenedIncludes[$path] = $path;
            }
        }

        return array_values($flattenedIncludes);
    }

    /**
     * @param array<object> $mainResources
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     *
     * @return array<object>
     */
    protected function loadRelatedResources(
        array $mainResources,
        array $config,
        array $context,
    ): array {
        $cacheKey = $this->buildCacheKey($mainResources, $config);

        if (isset($this->relationshipCache[$cacheKey])) {
            return $this->relationshipCache[$cacheKey];
        }

        $resolverClass = $config['resolver_class'] ?? null;

        if (is_string($resolverClass)) {
            $relatedResources = $this->loadRelatedResourcesViaResolver($mainResources, $resolverClass, $context);
            $this->relationshipCache[$cacheKey] = $relatedResources;

            return $relatedResources;
        }

        $providerServiceId = $config['provider_service_id'] ?? null;

        if (!is_string($providerServiceId)) {
            return [];
        }

        $provider = $this->getProvider($providerServiceId);

        if (!$provider) {
            return [];
        }

        $relatedResources = $provider instanceof BatchLoadableProviderInterface
            ? $this->loadRelatedResourcesInBatch($mainResources, $config, $context, $provider)
            : $this->loadRelatedResourcesSequentially($mainResources, $config, $context, $provider);

        $this->relationshipCache[$cacheKey] = $relatedResources;

        return $relatedResources;
    }

    /**
     * @param array<object> $parentResources
     * @param string $resolverClass
     * @param array<string, mixed> $context
     *
     * @return array<object>
     */
    protected function loadRelatedResourcesViaResolver(
        array $parentResources,
        string $resolverClass,
        array $context,
    ): array {
        if (!$this->resolverLocator->has($resolverClass)) {
            return [];
        }

        $resolver = $this->resolverLocator->get($resolverClass);

        if (!$resolver instanceof RelationshipResolverInterface) {
            return [];
        }

        if ($resolver instanceof PerItemRelationshipResolverInterface) {
            $perItemData = $resolver->resolvePerItem($parentResources, $context);

            // Store indexed by resolver class — mapped to path later
            $this->perItemRelationshipData[$resolverClass] = $perItemData;

            $allResources = [];

            foreach ($perItemData as $resources) {
                $allResources = array_merge($allResources, $resources);
            }

            return $allResources;
        }

        return $resolver->resolve($parentResources, $context);
    }

    /**
     * @param array<object> $mainResources
     * @param array<string, mixed> $config
     *
     * @return string
     */
    protected function buildCacheKey(array $mainResources, array $config): string
    {
        $resourceIds = array_map(
            fn ($resource) => spl_object_hash($resource),
            $mainResources,
        );

        $serviceId = $config['resolver_class'] ?? $config['provider_service_id'] ?? '';

        return sprintf(
            '%s:%s',
            $serviceId,
            implode(',', $resourceIds),
        );
    }

    /**
     * @param array<object> $mainResources
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     * @param \Spryker\ApiPlatform\Provider\BatchLoadableProviderInterface<object> $provider
     *
     * @return array<object>
     */
    protected function loadRelatedResourcesInBatch(
        array $mainResources,
        array $config,
        array $context,
        BatchLoadableProviderInterface $provider,
    ): array {
        $batchUriVariables = [];

        foreach ($mainResources as $mainResource) {
            $uriVariables = $this->buildUriVariables(
                $mainResource,
                $config['uri_variable_mappings'],
            );

            $batchUriVariables[] = $uriVariables;
        }

        if (!$batchUriVariables) {
            return [];
        }

        $uriVariables = [
            BatchLoadableProviderInterface::BATCH_DATA_KEY => $batchUriVariables,
        ];

        /** @phpstan-var array<object> */
        return $provider->provide(
            new GetCollection(),
            $uriVariables,
            $context,
        );
    }

    /**
     * @param array<object> $mainResources
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     * @param \ApiPlatform\State\ProviderInterface<object> $provider
     *
     * @return array<object>
     */
    protected function loadRelatedResourcesSequentially(
        array $mainResources,
        array $config,
        array $context,
        ProviderInterface $provider,
    ): array {
        $allRelatedResources = [];

        foreach ($mainResources as $mainResource) {
            $uriVariables = $this->buildUriVariables(
                $mainResource,
                $config['uri_variable_mappings'],
            );

            $itemResources = $this->hasArrayUriVariable($uriVariables)
                ? $this->loadForExpandedUriVariables($provider, $uriVariables, $context)
                : $this->loadForSingleUriVariables($provider, $uriVariables, $context);

            $allRelatedResources = array_merge($allRelatedResources, $itemResources);
            $this->trackPerItemData($config, $mainResource, $itemResources);
        }

        return $allRelatedResources;
    }

    /**
     * @param \ApiPlatform\State\ProviderInterface<object> $provider
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return array<object>
     */
    protected function loadForSingleUriVariables(
        ProviderInterface $provider,
        array $uriVariables,
        array $context,
    ): array {
        // It may happen that a relation can not be found. In this case we silently continue
        try {
            $relatedResources = $provider->provide(new GetCollection(), $uriVariables, $context);
        } catch (BadMethodCallException | GlueApiException) {
            $relatedResources = null;
        }

        if (is_array($relatedResources)) {
            return $relatedResources;
        }

        if ($relatedResources === null) {
            // Fall back to Get for providers that only support single-resource retrieval
            try {
                $singleResource = $provider->provide(new Get(), $uriVariables, $context);

                if ($singleResource !== null && is_object($singleResource)) {
                    return [$singleResource];
                }
            } catch (Throwable) {
                // Provider does not support Get with these variables
            }
        }

        return [];
    }

    /**
     * Fans out when a URI variable value is an array. Computes the cartesian product of all
     * array-valued URI variables and invokes the provider once per combination with a scalar
     * variable set. Used to resolve 1:N relationships keyed by an array of parent values
     * (e.g. abstract-products.attributeMap[product_concrete_ids] => concrete-products,
     * or a list of category node ids from a parent resource).
     *
     * @param \ApiPlatform\State\ProviderInterface<object> $provider
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return array<object>
     */
    protected function loadForExpandedUriVariables(
        ProviderInterface $provider,
        array $uriVariables,
        array $context,
    ): array {
        $itemResources = [];

        foreach ($this->expandArrayUriVariables($uriVariables) as $singleUriVariables) {
            $itemResources = array_merge(
                $itemResources,
                $this->loadForSingleUriVariables($provider, $singleUriVariables, $context),
            );
        }

        return $itemResources;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    protected function hasArrayUriVariable(array $uriVariables): bool
    {
        foreach ($uriVariables as $value) {
            if (is_array($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Computes the cartesian product of array-valued URI variables, producing one scalar
     * URI variable set per combination. Scalar variables are carried through unchanged.
     * Empty arrays short-circuit to no combinations (no provider invocations).
     *
     * @param array<string, mixed> $uriVariables
     *
     * @return array<int, array<string, mixed>>
     */
    protected function expandArrayUriVariables(array $uriVariables): array
    {
        $combinations = [[]];

        foreach ($uriVariables as $key => $value) {
            $nextCombinations = [];
            $values = is_array($value) ? array_values($value) : [$value];

            foreach ($combinations as $combination) {
                foreach ($values as $singleValue) {
                    $nextCombinations[] = $combination + [$key => $singleValue];
                }
            }

            $combinations = $nextCombinations;
        }

        return $combinations;
    }

    /**
     * Tracks per-item relationship data so the subscriber can inject nested relationships
     * into included resources (e.g., concrete-products into included items).
     *
     * @param array<string, mixed> $config
     * @param array<object> $resources
     */
    protected function trackPerItemData(array $config, object $mainResource, array $resources): void
    {
        $serviceId = $config['provider_service_id'] ?? '';

        if ($serviceId === '') {
            return;
        }

        $parentId = $this->resolveResourceIdentifier($mainResource);

        if ($parentId === null) {
            return;
        }

        $this->perItemRelationshipData[$serviceId][$parentId] = $resources;
    }

    protected function resolveResourceIdentifier(object $resource): ?string
    {
        $reflection = new ReflectionClass($resource);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $apiPropertyAttr = $property->getAttributes(ApiProperty::class)[0] ?? null;

            if ($apiPropertyAttr === null) {
                continue;
            }

            $apiProperty = $apiPropertyAttr->newInstance();

            if (!$apiProperty->isIdentifier()) {
                continue;
            }

            $value = $property->getValue($resource);

            return $value !== null ? (string)$value : null;
        }

        return null;
    }

    public function isResolverBased(string $resourceType, string $relationshipName): bool
    {
        $config = $this->getRelationshipConfig($resourceType, $relationshipName);

        return $config !== null && isset($config['resolver_class']);
    }

    /**
     * @param string $mainResourceType
     * @param string $relationshipName
     *
     * @return array<string, mixed>|null
     */
    protected function getRelationshipConfig(string $mainResourceType, string $relationshipName): ?array
    {
        $key = sprintf('%s.%s', $mainResourceType, $relationshipName);

        return $this->relationships[$key] ?? null;
    }

    /**
     * @param string $serviceId
     *
     * @return \ApiPlatform\State\ProviderInterface<object>|null
     */
    protected function getProvider(string $serviceId): ?ProviderInterface
    {
        if (!$this->providerLocator->has($serviceId)) {
            return null;
        }

        return $this->providerLocator->get($serviceId);
    }

    /**
     * @param object $mainResource
     * @param array<string, string> $mappings
     *
     * @return array<string, mixed>
     */
    protected function buildUriVariables(object $mainResource, array $mappings): array
    {
        $uriVariables = [];

        foreach ($mappings as $sourceProperty => $targetParameter) {
            if (!property_exists($mainResource, $sourceProperty)) {
                continue;
            }

            $value = $mainResource->$sourceProperty;

            if ($value !== null) {
                $uriVariables[$targetParameter] = $value;
            }
        }

        return $uriVariables;
    }
}
