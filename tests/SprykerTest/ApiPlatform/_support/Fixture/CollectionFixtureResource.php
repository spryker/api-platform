<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Fixture;

/**
 * Mirrors the shape the generator emits for an object collection: a bare `array` property whose
 * element type travels in the `@var` docblock, plus an untyped list and a scalar for the negative cases.
 */
final class CollectionFixtureResource
{
    /**
     * This docblock must stay in the exact shape `ClassGenerator::transformProperties()` emits —
     * `sprintf('@var array<int, %s>', $itemClassFqcn)` with a leading-backslash FQCN, pinned as a
     * string literal in `ClassGeneratorTest`. Nothing here enforces that the two stay in sync;
     * they are joined by convention, not by a test — change one, check the other.
     *
     * The explicit `int` key is load-bearing: `array<T>` yields a legacy PropertyInfo type with a
     * value type but no key type, which api-platform's `PropertyInfoToTypeInfoHelper` converts to
     * `array<mixed, T>` and then rejects with `"mixed" is not a valid array key type`.
     *
     * @var array<int, \SprykerTest\ApiPlatform\Fixture\CollectionFixtureItem>
     */
    public array $prices = [];

    public array $untypedList = [];

    public ?string $reference = null;
}
