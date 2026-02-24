<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * Generates OpenAPI tags from resource shortName for better API documentation organization.
 *
 * Examples:
 * - 'customers' → ['customers']
 * - 'customers-addresses' → ['customers-addresses']
 * - 'order-management-items' → ['order-management-items']
 *
 * These tags enable SwaggerUI filtering and logical grouping of API endpoints.
 */
class ResourceNameTagGenerator
{
    /**
     * @return array<string>
     */
    public function generateTags(string $shortName): array
    {
        return [$shortName];
    }
}
