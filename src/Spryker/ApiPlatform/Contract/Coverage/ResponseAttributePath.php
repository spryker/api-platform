<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The grammar for a response attribute path: a dot-separated walk through a JSON response body,
 * where each segment optionally carries an array index (`customers[0].firstName`) and the whole
 * path may open with a member selector into a top-level array (`[1].name`). `isValid()` is the
 * gate every asserted or truth-derived path passes through; `normalize()` reduces concrete indexes
 * to the `[]` wildcard so a truth path and an asserted path compare equal regardless of which
 * array element either one happened to touch; `segments()` decomposes a concrete path for walking
 * an actual response.
 */
class ResponseAttributePath
{
    protected const string PATTERN_VALID = '/^(\[\d+\]\.)?[A-Za-z_][A-Za-z0-9_]*(\[\d*\])?(\.[A-Za-z_][A-Za-z0-9_]*(\[\d*\])?)*$/';

    protected const string PATTERN_MEMBER_SELECTOR = '/^\[(\d+)\]\./';

    protected const string PATTERN_INDEX = '/\[\d+\]/';

    protected const string PATTERN_INDEXED_SEGMENT = '/^([A-Za-z_][A-Za-z0-9_]*)\[(\d+)\]$/';

    protected const string WILDCARD = '[]';

    protected const string FIRST_MEMBER_SELECTOR = '[0]';

    protected const int DEFAULT_MEMBER_INDEX = 0;

    public static function isValid(string $path): bool
    {
        return preg_match(static::PATTERN_VALID, $path) === 1;
    }

    public static function normalize(string $path): string
    {
        $withoutMember = (string)preg_replace(static::PATTERN_MEMBER_SELECTOR, '', $path);

        return (string)preg_replace(static::PATTERN_INDEX, static::WILDCARD, $withoutMember);
    }

    /**
     * A wildcard path names a set of array members rather than one, so it is a truth-set entry
     * compared as a normalized string and never a path an assertion can walk against an actual
     * response. Callers that walk paths reject it and point at {@see static::concreteMemberPath()}.
     */
    public static function isWildcard(string $path): bool
    {
        return str_contains($path, static::WILDCARD);
    }

    /**
     * The walkable form of a wildcard path: `lines[].sku` becomes `lines[0].sku`. The first member
     * is the one an assertion can name without knowing how many the response carries.
     */
    public static function concreteMemberPath(string $path): string
    {
        return str_replace(static::WILDCARD, static::FIRST_MEMBER_SELECTOR, $path);
    }

    public static function memberIndex(string $path): int
    {
        if (preg_match(static::PATTERN_MEMBER_SELECTOR, $path, $matches) !== 1) {
            return static::DEFAULT_MEMBER_INDEX;
        }

        return (int)$matches[1];
    }

    /**
     * Decomposes concrete indexes only, e.g. `customers[0].firstName` becomes
     * `['customers', 0, 'firstName']`. A wildcard segment (`lines[]`) has no index to decompose and
     * is returned as its own literal string segment; wildcard paths are truth-set entries compared
     * as normalized strings, never walked against an actual response, so that is not a defect.
     *
     * @return array<string|int>
     */
    public static function segments(string $path): array
    {
        $withoutMember = (string)preg_replace(static::PATTERN_MEMBER_SELECTOR, '', $path);
        $segments = [];
        foreach (explode('.', $withoutMember) as $part) {
            if (preg_match(static::PATTERN_INDEXED_SEGMENT, $part, $matches) === 1) {
                $segments[] = $matches[1];
                $segments[] = (int)$matches[2];

                continue;
            }
            $segments[] = $part;
        }

        return $segments;
    }
}
