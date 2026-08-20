<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Directory;

use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;

/**
 * Locates API-related directories inside the configured source directories.
 *
 * Modules keep their API artefacts at conventional, fixed-depth locations
 * (`{sourceDirectory}/{Module}/resources/api/{apiType}`), so the lookup uses targeted
 * `glob()` patterns instead of a recursive filesystem traversal. On a full Spryker
 * codebase a recursive scan visits tens of thousands of directories per call while the
 * glob only touches the paths that can match, which cuts container compile time by
 * multiple seconds. Results are memoized because several compiler passes perform the
 * same lookup during a single container build.
 *
 * Matches are re-validated to keep parity with the replaced Finder-based discovery:
 * literal path segments must match case-sensitively (on case-insensitive filesystems
 * `glob()` would otherwise find directories that case-sensitive CI/production hosts do
 * not), and directories reached through symlinked path segments are rejected (Symfony
 * Finder does not follow symlinks by default).
 */
class ApiDirectoryLocator
{
    /**
     * Relative glob patterns for resource schema directories (`resources/api/{apiType}`).
     * Cover the source directory being a module root itself, the conventional module
     * layout (`{Module}/resources/api/{apiType}`), and two-segment nesting — both
     * organization directories (`vendor/spryker-eco/{package}/resources/api/{apiType}`)
     * and the documented project-level layout (`src/Pyz/Glue/{Module}/resources/api/{apiType}`).
     *
     * @see https://docs.spryker.com/docs/integrations/spryker-api/api-platform/enablement
     *
     * @var array<string>
     */
    protected const array RESOURCE_DIRECTORY_PATTERNS = [
        '%s/resources/api/%s',
        '%s/*/resources/api/%s',
        '%s/*/*/resources/api/%s',
    ];

    /**
     * Relative glob patterns for API class directories (`Glue/{Module}/Api/{ApiType}`).
     * Cover the classic project layout (`src/Pyz/Glue/...`), a module `src` root, and
     * the module-checkout/vendor-package layout (`{Module}/src/{Org}/Glue/...`).
     *
     * @var array<string>
     */
    protected const array API_CLASS_DIRECTORY_PATTERNS = [
        '%s/Glue/*/Api/%s',
        '%s/src/*/Glue/*/Api/%s',
        '%s/*/src/*/Glue/*/Api/%s',
    ];

    /**
     * @var array<string, array<string>>
     */
    protected array $locatedDirectoriesCache = [];

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<string>
     */
    public function locateResourceSchemaDirectories(array $sourceDirectories, string $apiType): array
    {
        return $this->locateDirectories(static::RESOURCE_DIRECTORY_PATTERNS, $sourceDirectories, strtolower($apiType));
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<string>
     */
    public function locateApiClassDirectories(array $sourceDirectories, string $apiType): array
    {
        return $this->locateDirectories(
            static::API_CLASS_DIRECTORY_PATTERNS,
            $sourceDirectories,
            ApiTypeNormalizer::normalizeForGeneration($apiType),
        );
    }

    /**
     * @param array<string> $patterns
     * @param array<string> $sourceDirectories
     *
     * @return array<string>
     */
    protected function locateDirectories(array $patterns, array $sourceDirectories, string $apiType): array
    {
        $cacheKey = md5(serialize([$patterns, $sourceDirectories, $apiType]));

        if (isset($this->locatedDirectoriesCache[$cacheKey])) {
            return $this->locatedDirectoriesCache[$cacheKey];
        }

        $directories = [];

        foreach ($sourceDirectories as $sourceDirectory) {
            $sourceDirectory = rtrim($sourceDirectory, '/');

            if (!is_dir($sourceDirectory)) {
                continue;
            }

            $directories = array_merge($directories, $this->locateInSourceDirectory($patterns, $sourceDirectory, $apiType));
        }

        $directories = array_values(array_unique($directories));

        return $this->locatedDirectoriesCache[$cacheKey] = $directories;
    }

    /**
     * @param array<string> $patterns
     *
     * @return array<string>
     */
    protected function locateInSourceDirectory(array $patterns, string $sourceDirectory, string $apiType): array
    {
        $directories = [];

        foreach ($patterns as $pattern) {
            $directories = array_merge(
                $directories,
                $this->matchPattern($pattern, $sourceDirectory, $apiType),
            );
        }

        return $directories;
    }

    /**
     * The glob pattern gets the source directory and API type with glob metacharacters escaped so
     * they are always matched literally; segment validation uses the unescaped pattern.
     *
     * @return array<string>
     */
    protected function matchPattern(string $pattern, string $sourceDirectory, string $apiType): array
    {
        $globPattern = sprintf($pattern, $this->escapeGlobMetacharacters($sourceDirectory), $this->escapeGlobMetacharacters($apiType));
        $literalPattern = sprintf($pattern, $sourceDirectory, $apiType);

        $directories = [];

        foreach (glob($globPattern, GLOB_ONLYDIR) ?: [] as $matchedDirectory) {
            $realPath = $this->validateMatch($matchedDirectory, $literalPattern, $sourceDirectory);

            if ($realPath !== null) {
                $directories[] = $realPath;
            }
        }

        return $directories;
    }

    /**
     * Validates a glob match segment by segment below the source directory and resolves it to its
     * real path. Literal pattern segments must exist on disk with the exact requested casing, and
     * no segment may be a symlink — both mirror the behavior of the replaced Finder-based lookup.
     */
    protected function validateMatch(string $matchedDirectory, string $literalPattern, string $sourceDirectory): ?string
    {
        $prefixLength = strlen($sourceDirectory) + 1;
        $patternSegments = explode('/', substr($literalPattern, $prefixLength));
        $matchedSegments = explode('/', substr($matchedDirectory, $prefixLength));
        $currentPath = $sourceDirectory;

        foreach ($matchedSegments as $index => $matchedSegment) {
            $currentPath = sprintf('%s/%s', $currentPath, $matchedSegment);

            if (!$this->isCanonicalSegment($currentPath, $patternSegments[$index] ?? '')) {
                return null;
            }
        }

        $realPath = realpath($matchedDirectory);

        return $realPath === false ? null : $realPath;
    }

    protected function isCanonicalSegment(string $segmentPath, string $patternSegment): bool
    {
        if (is_link($segmentPath)) {
            return false;
        }

        if (str_contains($patternSegment, '*')) {
            return true;
        }

        // `glob()` reports literal segments in the pattern's spelling even when the on-disk casing
        // differs (case-insensitive filesystems), so the real directory listing is the only
        // reliable source for a case-sensitive comparison.
        $directoryEntries = scandir(dirname($segmentPath)) ?: [];

        return in_array($patternSegment, $directoryEntries, true);
    }

    protected function escapeGlobMetacharacters(string $path): string
    {
        return addcslashes($path, '*?[]');
    }
}
