<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Relationship;

/**
 * Interface for custom relationship resolvers that handle complex relationship logic.
 *
 * Use this when the standard URI variable mapping approach is insufficient,
 * for example when related resources must be extracted from nested arrays
 * or require custom data aggregation.
 *
 * Implementations are referenced in resource YAML files via the `resolverClass` property:
 * ```yaml
 * includes:
 *   - relationshipName: merchants
 *     resolverClass: Spryker\Glue\Merchant\Relationship\OrderMerchantsRelationshipResolver
 * ```
 */
interface RelationshipResolverInterface
{
    /**
     * Resolves related resources for the given parent resources.
     *
     * @param array<object> $parentResources The parent resource instances to resolve relationships for
     * @param array<string, mixed> $context Request context including locale, store, and other metadata
     *
     * @return array<object> The resolved related resource instances
     */
    public function resolve(array $parentResources, array $context): array;
}
