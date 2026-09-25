<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use ReflectionClass;

/**
 * Resolves a generated resource class back to the `.resource.yml` schema files it was generated
 * from, by parsing the `Source schema files:` lines the generator writes into the file docblock.
 * A schema defect is only actionable if the report names the file to edit, and the generated header
 * is the only place that mapping exists.
 *
 * The sibling `Validation schema files:` lines share the line format and are excluded by requiring
 * the `.resource.yml` suffix.
 */
class SchemaSourceResolver
{
    protected const string SOURCE_LINE_PATTERN = '#^ \* - (?<path>/.+\.resource\.yml)$#m';

    /**
     * @param class-string $resourceClass
     * @param string $applicationRoot Strips this prefix so the report prints repo-relative paths.
     *
     * @return array<string>
     */
    public function schemaFilesFor(string $resourceClass, string $applicationRoot = ''): array
    {
        $file = (new ReflectionClass($resourceClass))->getFileName();
        if ($file === false) {
            return [];
        }

        preg_match_all(static::SOURCE_LINE_PATTERN, (string)file_get_contents($file), $matches);

        return array_map(
            fn (string $path): string => $this->relativize($path, $applicationRoot),
            $matches['path'],
        );
    }

    /**
     * @param array<class-string> $resourceClasses
     *
     * @return array<string>
     */
    public function schemaFilesForAll(array $resourceClasses, string $applicationRoot = ''): array
    {
        $schemaFiles = [];

        foreach ($resourceClasses as $resourceClass) {
            $schemaFiles = array_merge($schemaFiles, $this->schemaFilesFor($resourceClass, $applicationRoot));
        }

        $schemaFiles = array_values(array_unique($schemaFiles));
        sort($schemaFiles);

        return $schemaFiles;
    }

    protected function relativize(string $path, string $applicationRoot): string
    {
        if ($applicationRoot === '' || !str_starts_with($path, $applicationRoot)) {
            return $path;
        }

        return ltrim(substr($path, strlen($applicationRoot)), '/');
    }
}
