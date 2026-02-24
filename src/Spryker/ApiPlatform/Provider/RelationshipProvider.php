<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements \ApiPlatform\State\ProviderInterface<object>
 */
class RelationshipProvider implements ProviderInterface
{
    /**
     * @param \ApiPlatform\State\ProviderInterface<object> $decorated
     * @param \Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolverInterface $relationshipResolver
     * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
     */
    public function __construct(
        protected ProviderInterface $decorated,
        protected ApiPlatformRelationshipResolverInterface $relationshipResolver,
        protected RequestStack $requestStack,
    ) {
    }

    /**
     * @param \ApiPlatform\Metadata\Operation $operation
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

        // Ensure request is in context
        $request = $this->requestStack->getCurrentRequest();

        if (!isset($context['request']) && $request) {
            $context['request'] = $request;
        }

        // Read from request attributes (set by JsonApiProvider)
        $requestedIncludes = $request?->attributes->get('_api_included', []);

        // Fallback to parsing from context if not set by JsonApiProvider
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

        foreach ($relationships as $relationshipName => $relationship) {
            if (is_object($result) && property_exists($result, $relationshipName)) {
                $result->{$relationshipName} = $relationship;
            }
        }

        return $result;
    }
}
