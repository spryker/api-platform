<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Matches what a developer types on the command line against a generated resource short name.
 *
 * Casing, separators and a trailing plural `s` are ignored, so the module spelling
 * (`WishlistItems`) and the short name (`wishlist-items`) select the same resource. Matching is
 * exact once normalised: `Wishlist` selects `wishlists` and not `wishlist-items`.
 */
class ResourceNameMatcher
{
    /**
     * Selects the resources named by the filters. Returns every resource when no filter is given.
     *
     * @param array<string> $resourceNames
     * @param array<string> $filters
     *
     * @return array<string>
     */
    public function match(array $resourceNames, array $filters): array
    {
        if ($filters === []) {
            return $resourceNames;
        }

        $normalisedFilters = array_map([$this, 'normalise'], $filters);

        return array_values(array_filter(
            $resourceNames,
            fn (string $resourceName): bool => in_array($this->normalise($resourceName), $normalisedFilters, true),
        ));
    }

    /**
     * The filters that name no known resource, so the caller can fail on a typo instead of
     * silently reporting on nothing.
     *
     * @param array<string> $resourceNames
     * @param array<string> $filters
     *
     * @return array<string>
     */
    public function unmatchedFilters(array $resourceNames, array $filters): array
    {
        $normalisedResourceNames = array_map([$this, 'normalise'], $resourceNames);

        return array_values(array_filter(
            $filters,
            fn (string $filter): bool => !in_array($this->normalise($filter), $normalisedResourceNames, true),
        ));
    }

    protected function normalise(string $value): string
    {
        $value = strtolower((string)preg_replace('/[^a-zA-Z0-9]/', '', $value));

        return rtrim($value, 's');
    }
}
