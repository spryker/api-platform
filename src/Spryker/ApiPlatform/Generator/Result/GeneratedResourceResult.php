<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Result;

/**
 * Result of generating a single resource: the main resource class plus the companion
 * value-object classes emitted for its typed nested object properties (a `type: object`
 * property that declares its own `properties`, e.g. cart `totals`). The companion classes
 * are written as standalone sibling files next to the resource.
 */
class GeneratedResourceResult
{
    /**
     * @param string $mainClassCode Generated PHP for the resource class itself.
     * @param array<string, string> $nestedObjectClasses Companion value-object classes, keyed
     *     by class name → generated PHP code. Empty when the resource has no typed nested objects.
     */
    public function __construct(
        protected readonly string $mainClassCode,
        protected readonly array $nestedObjectClasses = [],
    ) {
    }

    public function getMainClassCode(): string
    {
        return $this->mainClassCode;
    }

    /**
     * @return array<string, string>
     */
    public function getNestedObjectClasses(): array
    {
        return $this->nestedObjectClasses;
    }
}
