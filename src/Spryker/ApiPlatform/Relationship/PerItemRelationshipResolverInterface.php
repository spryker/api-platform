<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Relationship;

/**
 * Extension of RelationshipResolverInterface for resolvers that need per-item
 * relationship scoping in collection responses.
 *
 * Standard resolvers return a flat list of related resources which are attached
 * to ALL parent items in the response. Per-item resolvers return resources grouped
 * by parent identifier, allowing each parent item to have its own specific
 * relationship linkage.
 *
 * Use this when each parent resource has different related resources
 * (e.g., each company-user has a different customer, company, or business unit).
 */
interface PerItemRelationshipResolverInterface extends RelationshipResolverInterface
{
    /**
     * Resolves related resources grouped by parent resource identifier.
     *
     * @param array<object> $parentResources The parent resource instances
     * @param array<string, mixed> $context Request context
     *
     * @return array<string, array<object>> Map of parent identifier => related resources
     */
    public function resolvePerItem(array $parentResources, array $context): array;
}
