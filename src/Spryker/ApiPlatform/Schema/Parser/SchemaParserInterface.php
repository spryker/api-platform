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

    /**
     * Normalizes a raw properties map from an object schema file using the same type-alias mapping
     * that is applied to resource properties (e.g. `str` → `string`, `int` → `integer`).
     *
     * @param array<string, mixed> $properties Raw properties from the YAML `object.properties` key
     * @param string $filePath Source file path (used for validation error messages)
     *
     * @return array<string, array<string, mixed>> Normalized property map keyed by property name
     */
    public function normalizeObjectProperties(array $properties, string $filePath): array;

    /**
     * Detects the source layer (`project`, `feature`, or `core`) from the given file path.
     *
     * @param string $filePath Absolute path to the schema file
     *
     * @return string One of: `project`, `feature`, `core`
     */
    public function detectLayer(string $filePath): string;
}
