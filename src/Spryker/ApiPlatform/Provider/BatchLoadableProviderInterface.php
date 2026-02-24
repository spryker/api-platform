<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * Providers implementing this interface support batch loading of related resources.
 *
 * Instead of calling the provider N times (once per main resource), the resolver
 * will collect all URI variables and call provideBatch once with all parameters.
 *
 * This prevents N+1 query problems when loading relationships.
 *
 * Example:
 * Without batch loading:
 * - Call provider for customer 1 -> get addresses for customer 1
 * - Call provider for customer 2 -> get addresses for customer 2
 * - Call provider for customer 3 -> get addresses for customer 3
 * Total: 3 database queries
 *
 * With batch loading:
 * - Call provideBatch with [customer1, customer2, customer3] -> get all addresses
 * Total: 1 database query
 *
 * @template T of object
 * @extends \ApiPlatform\State\ProviderInterface<T>
 */
interface BatchLoadableProviderInterface extends ProviderInterface
{
    /**
     * Provide resources for multiple sets of URI variables in a single call.
     *
     * @param \ApiPlatform\Metadata\Operation $operation
     * @param array<array<string, mixed>> $batchUriVariables Array of URI variable sets
     * @param array<string, mixed> $context
     *
     * @return array<array<object>> Array of resource arrays, indexed by batch position
     */
    public function provideBatch(Operation $operation, array $batchUriVariables, array $context): array;
}
