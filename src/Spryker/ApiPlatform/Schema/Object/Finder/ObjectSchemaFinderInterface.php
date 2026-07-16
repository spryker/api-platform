<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Object\Finder;

use Generator;

/**
 * Finds canonical object schema files for API resources.
 */
interface ObjectSchemaFinderInterface
{
    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findObjectSchemas(string $apiType): Generator;

    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findObjectValidationSchemas(string $apiType): Generator;

    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findCentralObjectSchemas(string $apiType): Generator;

    /**
     * @return \Generator<\SplFileInfo>
     */
    public function findCentralObjectValidationSchemas(string $apiType): Generator;
}
