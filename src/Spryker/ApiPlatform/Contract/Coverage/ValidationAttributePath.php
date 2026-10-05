<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * Brings a validator property path into the dotted form a validation annotation and the truth use:
 * brackets become dots and list indexes are dropped, because an index is data, not contract.
 * `productConfigurationInstance[prices][0][currency][code]` becomes
 * `productConfigurationInstance.prices.currency.code`, and `items[0].sku` becomes `items.sku`.
 *
 * A named bracket segment is either a `Collection` field, which the truth keeps as a segment, or a
 * key of a map validated through `All`, which the truth drops like a list index. The path alone
 * cannot tell them apart, so {@see self::matches()} accepts either reading.
 */
class ValidationAttributePath
{
    protected const string PATTERN_BRACKET_SEGMENT = '/\[([^\]]*)\]/';

    protected const string PATTERN_SEGMENT = '/\[([^\]]*)\]|([^.\[\]]+)/';

    /**
     * @var non-empty-string
     */
    protected const string SEPARATOR = '.';

    public static function normalize(string $propertyPath): string
    {
        $dotted = (string)preg_replace(static::PATTERN_BRACKET_SEGMENT, static::SEPARATOR . '$1', $propertyPath);

        $segments = array_filter(
            explode(static::SEPARATOR, $dotted),
            static fn (string $segment): bool => $segment !== '' && !ctype_digit($segment),
        );

        return implode(static::SEPARATOR, $segments);
    }

    /**
     * Whether a validator property path names the dotted attribute of a declaration or the truth,
     * reading each named bracket segment as a field or as a dropped map key.
     * `unitPriceMap[any-group-key]` names `unitPriceMap`, and `prices[0][currency]` names
     * `prices.currency`.
     */
    public static function matches(string $propertyPath, string $attribute): bool
    {
        preg_match_all(static::PATTERN_SEGMENT, $propertyPath, $matches, PREG_SET_ORDER);

        $segments = [];
        foreach ($matches as $match) {
            $isBracketed = ($match[2] ?? '') === '';
            $segment = $isBracketed ? $match[1] : $match[2];
            if ($segment === '' || ctype_digit($segment)) {
                continue;
            }
            $segments[] = ['name' => $segment, 'isOptional' => $isBracketed];
        }

        return static::segmentsMatch($segments, explode(static::SEPARATOR, $attribute));
    }

    /**
     * @param array<array{name: string, isOptional: bool}> $segments
     * @param array<string> $attributeSegments
     */
    protected static function segmentsMatch(array $segments, array $attributeSegments): bool
    {
        if ($segments === []) {
            return $attributeSegments === [];
        }

        $segment = array_shift($segments);

        if ($attributeSegments !== [] && $attributeSegments[0] === $segment['name'] && static::segmentsMatch($segments, array_slice($attributeSegments, 1))) {
            return true;
        }

        return $segment['isOptional'] && static::segmentsMatch($segments, $attributeSegments);
    }
}
