<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validation\Finder;

use Generator;
use SplFileInfo;

/**
 * Finds validation schema files for API resources.
 */
interface ValidationSchemaFinderInterface
{
    public function findValidationSchema(
        string $resourceName,
        string $apiType,
        string $layer,
        string $sourceDirectory,
    ): ?SplFileInfo;

    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findAllValidationSchemas(string $apiType): Generator;

    /**
     * Get diagnostic information about validation schema search for troubleshooting.
     *
     * @return array<string, mixed>
     */
    public function getValidationDiagnosticInfo(string $apiType): array;
}
