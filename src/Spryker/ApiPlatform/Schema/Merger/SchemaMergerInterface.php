<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Merger;

/**
 * Merges schemas with layered override strategy following Spryker's core → feature → project hierarchy.
 */
interface SchemaMergerInterface
{
    /**
     * @param array<array<string, mixed>> $schemas
     *
     * @return array<string, mixed>
     */
    public function merge(array $schemas, string $resourceName, string $apiType): array;
}
