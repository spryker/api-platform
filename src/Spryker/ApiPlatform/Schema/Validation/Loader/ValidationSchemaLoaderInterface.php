<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validation\Loader;

use SplFileInfo;

/**
 * Loads and parses validation schema files.
 */
interface ValidationSchemaLoaderInterface
{
    /**
     * @param \SplFileInfo $file
     *
     * @return array<string, mixed>
     */
    public function load(SplFileInfo $file): array;
}
