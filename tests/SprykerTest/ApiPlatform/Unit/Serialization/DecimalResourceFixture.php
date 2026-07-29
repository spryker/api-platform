<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Serialization;

/**
 * Mirrors the three property shapes generated resources use for decimal-backed fields: `type: string`,
 * `type: array` (payload passed through untouched) and integer-typed.
 */
class DecimalResourceFixture
{
    public ?string $amount = null;

    /**
     * @var array<mixed>
     */
    public array $items = [];

    public ?int $quantity = null;
}
