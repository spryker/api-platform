<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Metadata;

/**
 * Short-name-keyed lookups are lossy: distinct resources may declare the same JSON:API short name
 * (`orders` is declared by both the Orders and the CustomersOrders resource), and a map keyed by
 * short name can hold only one of them — the entry compiled last into the resource class index
 * wins, for the class and the sort priority alike.
 */
interface ResourceClassIndexProviderInterface
{
    /**
     * Resolves resource short names to resource classes for the given code bucket.
     *
     * @return array<string, class-string>
     */
    public function getResourceClassIndex(string $codeBucket): array;

    /**
     * Resolves resource short names to their `includedSortPriority` extra property for the given code bucket.
     *
     * @return array<string, int>
     */
    public function getIncludedSortPriorityIndex(string $codeBucket): array;
}
