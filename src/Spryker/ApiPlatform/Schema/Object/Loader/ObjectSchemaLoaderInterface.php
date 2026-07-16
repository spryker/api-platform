<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Object\Loader;

use SplFileInfo;

/**
 * Loads and normalizes a single *.object.yml canonical-object schema file.
 */
interface ObjectSchemaLoaderInterface
{
    /**
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     *
     * @return array{name: string, extends: string|null, omit: array<int, string>, properties: array<string, array<string, mixed>>, layer: string, sourceFile: string}
     */
    public function load(SplFileInfo $file, ?string $layerOverride = null): array;

    public function detectLayer(string $filePath): string;
}
