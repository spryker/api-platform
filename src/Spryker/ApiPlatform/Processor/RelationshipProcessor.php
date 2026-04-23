<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Processor;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Throwable;

/**
 * Decorates the processor pipeline to resolve relationship includes
 * for write operations (POST, PATCH, DELETE) that return a resource.
 *
 * Mirrors the RelationshipProvider decorator on the read side.
 *
 * @implements \ApiPlatform\State\ProcessorInterface<mixed, mixed>
 */
class RelationshipProcessor implements ProcessorInterface
{
    protected const string REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS = '_spryker_resolved_relationships';

    /**
     * @param \ApiPlatform\State\ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        protected ProcessorInterface $decorated,
        protected ApiPlatformRelationshipResolverInterface $relationshipResolver,
        protected ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws \Throwable
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        $representativeResource = $this->extractRepresentativeResource($result);

        if ($representativeResource === null) {
            return $result;
        }

        $request = $context['request'] ?? null;

        if (!$request instanceof Request) {
            return $result;
        }

        $requestedIncludes = $this->relationshipResolver->parseIncludeParameter($context);

        if (!$requestedIncludes) {
            return $result;
        }

        $resourcesToResolve = is_array($result) ? $result : [$result];
        $resourceType = $this->resolveResourceType($representativeResource, $operation);

        try {
            $relationships = $this->relationshipResolver->resolveRelationships(
                $resourceType,
                $resourcesToResolve,
                $requestedIncludes,
                $context,
            );
        } catch (AccessDeniedException | AuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            if ($this->logger !== null) {
                $this->logger->error('Relationship resolution failed', ['exception' => $throwable]);
            }

            return $result;
        }

        // Also resolve nested includes starting from any resources the processor already set
        // (e.g. guest-cart-items set via setResolvedRelationships). This enables POST responses
        // to include nested relationships like concrete-products under guest-cart-items.
        $relationships = array_merge(
            $relationships,
            $this->resolveRelationshipsForPreResolvedItems($request, $requestedIncludes, $operation, $context),
        );

        if ($relationships) {
            $existing = $request->attributes->get(static::REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS, []);
            $request->attributes->set(
                static::REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS,
                array_merge($existing, $relationships),
            );
        }

        // Propagate per-item relationship data for the subscriber to inject nested relationships
        // into included resources (e.g., relationships.concrete-products in included items).
        $perItemData = $this->relationshipResolver->getPerItemRelationshipData();

        if ($perItemData) {
            $perItemByPath = $this->mapPerItemDataToPaths($relationships, $perItemData);
            $existing = $request->attributes->get('_spryker_per_item_relationships', []);
            $request->attributes->set('_spryker_per_item_relationships', array_merge($existing, $perItemByPath));
        }

        return $result;
    }

    /**
     * Resolves nested includes for resources already resolved by the processor (e.g. guest-cart-items
     * set via setResolvedRelationships). This enables POST responses to include nested relationships
     * like concrete-products under guest-cart-items when only the cart is returned by the processor.
     *
     * @param array<string> $requestedIncludes
     * @param array<string, mixed> $context
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Symfony\Component\Security\Core\Exception\AuthenticationException
     *
     * @return array<string, array<object>>
     */
    protected function resolveRelationshipsForPreResolvedItems(
        Request $request,
        array $requestedIncludes,
        Operation $operation,
        array $context,
    ): array {
        $preResolved = $request->attributes->get(static::REQUEST_ATTRIBUTE_RESOLVED_RELATIONSHIPS, []);
        $relationships = [];

        foreach ($preResolved as $preResolvedResources) {
            if (!is_array($preResolvedResources) || $preResolvedResources === []) {
                continue;
            }

            $firstResource = reset($preResolvedResources);

            if (!is_object($firstResource)) {
                continue;
            }

            $resourceType = $this->resolveResourceType($firstResource, $operation);

            try {
                $nestedRelationships = $this->relationshipResolver->resolveRelationships(
                    $resourceType,
                    $preResolvedResources,
                    $requestedIncludes,
                    $context,
                );
            } catch (AccessDeniedException | AuthenticationException $exception) {
                throw $exception;
            } catch (Throwable $throwable) {
                if ($this->logger !== null) {
                    $this->logger->error('Nested relationship resolution failed', ['exception' => $throwable]);
                }

                continue;
            }

            $relationships = array_merge($relationships, $nestedRelationships);
        }

        return $relationships;
    }

    /**
     * Maps per-item relationship data from resolver class keys to relationship path keys,
     * matching resource arrays by object identity.
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
            foreach ($perItemData as $parentMapping) {
                $flatResources = [];

                foreach ($parentMapping as $parentResources) {
                    $flatResources = array_merge($flatResources, $parentResources);
                }

                if ($flatResources === $resources) {
                    $result[$path] = $parentMapping;

                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Returns the first object from $result to use for resource-type resolution,
     * or null when $result cannot be resolved (not an object and not an array of objects).
     */
    protected function extractRepresentativeResource(mixed $result): ?object
    {
        if (is_object($result)) {
            return $result;
        }

        if (!is_array($result)) {
            return null;
        }

        $first = reset($result);

        if (!is_object($first)) {
            return null;
        }

        return $first;
    }

    /**
     * Resolves the resource type from the returned object's #[ApiResource] attribute.
     * When a processor returns a different resource type than its operation
     * (e.g. cart-items processor returning a carts resource), the actual
     * object type is used for relationship resolution.
     */
    protected function resolveResourceType(object $result, Operation $operation): string
    {
        $reflection = new ReflectionClass($result);
        $apiResourceAttributes = $reflection->getAttributes(ApiResource::class);

        if ($apiResourceAttributes !== []) {
            $shortName = $apiResourceAttributes[0]->newInstance()->getShortName();

            if ($shortName !== null) {
                return $shortName;
            }
        }

        return (string)$operation->getShortName();
    }
}
