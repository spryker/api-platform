<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Provider;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Psr\Container\ContainerInterface;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
class RelationshipProvider implements ProviderInterface
{
    protected const string REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS = '_spryker_resolved_relationships';

    /**
     * @param \ApiPlatform\State\ProviderInterface<object> $decorated
     */
    public function __construct(
        protected ProviderInterface $decorated,
        protected ApiPlatformRelationshipResolverInterface $relationshipResolver,
        protected ContainerInterface $iriConverterLocator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return object|array<object>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $result = $this->decorated->provide($operation, $uriVariables, $context);

        if ($result === null) {
            return null;
        }

        $request = $context['request'] ?? null;

        $requestedIncludes = $request instanceof Request ? $request->attributes->get('_api_included', []) : [];

        if (!$requestedIncludes) {
            $requestedIncludes = $this->relationshipResolver->parseIncludeParameter($context);
        }

        if (!$requestedIncludes) {
            return $result;
        }

        $resourceType = (string)$operation->getShortName();
        $resources = is_array($result) ? $result : [$result];

        $relationships = $this->relationshipResolver->resolveRelationships(
            $resourceType,
            $resources,
            $requestedIncludes,
            $context,
        );

        // All resolved relationships are injected via the subscriber so that
        // relationship linkage and included resources appear correctly in the
        // JSON:API response regardless of the property's readable flag.
        if ($relationships) {
            $existing = $request?->attributes->get(static::REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS, []) ?? [];
            $request?->attributes->set(
                static::REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS,
                array_merge($existing, $relationships),
            );
        }

        // Map per-item relationship data from resolver classes to relationship paths
        $perItemData = $this->relationshipResolver->getPerItemRelationshipData();

        if ($perItemData) {
            $perItemByPath = $this->mapPerItemDataToPaths($relationships, $perItemData);
            $existing = $request?->attributes->get('_spryker_per_item_relationships', []) ?? [];
            $request?->attributes->set(
                '_spryker_per_item_relationships',
                array_merge($existing, $perItemByPath),
            );
        }

        return $result;
    }

    /**
     * Maps per-item relationship data from resolver class keys to relationship path keys.
     *
     * @param array<string, array<object>> $relationships Path → resources
     * @param array<string, array<string, array<object>>> $perItemData Resolver class → parent ID → resources
     *
     * @return array<string, array<string, array<object>>> Path → parent ID → resources
     */
    protected function mapPerItemDataToPaths(array $relationships, array $perItemData): array
    {
        $result = [];

        foreach ($relationships as $path => $resources) {
            foreach ($perItemData as $resolverClass => $parentMapping) {
                // Match by checking if the resources returned for this path are the same objects
                $flatResources = [];

                foreach ($parentMapping as $parentResources) {
                    $flatResources = array_merge($flatResources, $parentResources);
                }

                if ($this->deduplicateByIri($flatResources) === $resources) {
                    $result[$path] = $parentMapping;

                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<object> $resources
     *
     * @return array<object>
     */
    protected function deduplicateByIri(array $resources): array
    {
        $seen = [];
        $result = [];

        foreach ($resources as $resource) {
            try {
                $iri = $this->getIriConverter()->getIriFromResource($resource);
            } catch (Throwable) {
                $iri = spl_object_hash($resource);
            }

            if (isset($seen[$iri])) {
                continue;
            }

            $seen[$iri] = true;
            $result[] = $resource;
        }

        return $result;
    }

    protected function getIriConverter(): IriConverterInterface
    {
        return $this->iriConverterLocator->get('iriConverter');
    }
}
