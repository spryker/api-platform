<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Relationship;

interface ApiPlatformRelationshipResolverInterface
{
    /**
     * @param string $mainResourceType Main resource type (e.g., "customers")
     * @param array<object> $mainResources Main resource instances
     * @param array<string> $requestedIncludes List of relationship names to include
     * @param array<string, mixed> $context Request context
     *
     * @return array<string, array<object>> Map of relationship name to related resources
     */
    public function resolveRelationships(
        string $mainResourceType,
        array $mainResources,
        array $requestedIncludes,
        array $context,
    ): array;

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string>
     */
    public function parseIncludeParameter(array $context): array;

    /**
     * Returns true when the given relationship uses a custom resolver class
     * instead of a standard URI-template-based provider.
     */
    public function isResolverBased(string $resourceType, string $relationshipName): bool;

    /**
     * Returns per-item relationship data collected during resolution.
     *
     * @return array<string, array<string, array<object>>>
     */
    public function getPerItemRelationshipData(): array;
}
