<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Relationship;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use Psr\Container\ContainerInterface;
use Spryker\ApiPlatform\Provider\BatchLoadableProviderInterface;

class ApiPlatformRelationshipResolver implements ApiPlatformRelationshipResolverInterface
{
    protected const int MAX_NESTING_DEPTH = 3;

    /**
     * @var array<string, array<object>>
     */
    protected array $relationshipCache = [];

    /**
     * @param array<string, array<string, mixed>> $relationships
     * @param \Psr\Container\ContainerInterface $providerLocator
     */
    public function __construct(
        protected array $relationships,
        protected ContainerInterface $providerLocator,
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
            );

            $relationships = array_merge($relationships, $resolvedRelationships);
        }

        return $relationships;
    }

    /**
     * @param string $mainResourceType
     * @param array<object> $mainResources
     * @param string $includePath
     * @param array<string, mixed> $context
     * @param array<string, array<object>> $processedIncludes
     *
     * @return array<string, array<object>>
     */
    protected function resolveNestedRelationship(
        string $mainResourceType,
        array $mainResources,
        string $includePath,
        array $context,
        array &$processedIncludes,
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

            $relationships[$currentPath] = $relatedResources;
            $processedIncludes[$currentPath] = $relatedResources;
            $currentResources = $relatedResources;
            $currentResourceType = $relationshipConfig['target_resource_type'] ?? '';
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

        $provider = $this->getProvider($config['provider_service_id']);

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

        return sprintf(
            '%s:%s',
            $config['provider_service_id'],
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

        $batchResults = $provider->provideBatch(
            new GetCollection(),
            $batchUriVariables,
            $context,
        );

        $allRelatedResources = [];

        foreach ($batchResults as $relatedResources) {
            $allRelatedResources = array_merge($allRelatedResources, $relatedResources);
        }

        return $allRelatedResources;
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

            $relatedResources = $provider->provide(
                new GetCollection(),
                $uriVariables,
                $context,
            );

            if (is_array($relatedResources)) {
                $allRelatedResources = array_merge($allRelatedResources, $relatedResources);
            }
        }

        return $allRelatedResources;
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
            $value = $mainResource->$sourceProperty ?? null;

            if ($value !== null) {
                $uriVariables[$targetParameter] = $value;
            }
        }

        return $uriVariables;
    }
}
