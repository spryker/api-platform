<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Parser;

use SplFileInfo;

/**
 * Parses raw schema arrays into normalized structure for consistent processing.
 */
interface SchemaParserInterface
{
    /**
     * @param array<string, mixed> $rawSchema
     * @param array<string, mixed> $validationSchemas Map of resource name to validation schema
     *
     * @return array<string, mixed>
     */
    public function parse(array $rawSchema, SplFileInfo $file, array $validationSchemas = []): array;
}
