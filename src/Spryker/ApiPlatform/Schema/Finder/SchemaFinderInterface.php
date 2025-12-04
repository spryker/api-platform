<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Finder;

use Generator;

/**
 * Interface for finding API schema files.
 *
 * Implementations should use generators to yield files for memory efficiency.
 */
interface SchemaFinderInterface
{
    /**
     * Find all schema files for a given ApiType.
     *
     * Yields SplFileInfo objects for each schema file found.
     * Files are searched in all configured source directories.
     */
    public function findSchemaFiles(string $apiType): Generator;

    /**
     * Get diagnostic information about schema file search for troubleshooting.
     *
     * @param string $apiType
     *
     * @return array<string, mixed>
     */
    public function getDiagnosticInfo(string $apiType): array;
}
