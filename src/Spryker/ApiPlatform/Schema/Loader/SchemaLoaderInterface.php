<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Loader;

use SplFileInfo;

/**
 * Interface for loading schema files.
 *
 * Implementations handle specific file formats (YAML) and convert
 * them to a normalized array structure.
 */
interface SchemaLoaderInterface
{
    /**
     * Load a schema file and return its contents as a normalized array.
     *
     * @return array<string, mixed> Normalized schema data
     */
    public function load(SplFileInfo $file): array;

    /**
     * Check if this loader supports the given file.
     */
    public function supports(SplFileInfo $file): bool;
}
