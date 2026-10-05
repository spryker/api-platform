<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Attribute;

use Attribute;

/**
 * Marks a test whose successful requests send request attributes of the input operation its
 * {@see CoversApiOperation} names. Without arguments it claims every writable attribute of the
 * operation; with paths (`'currency'`, `'salesUnit.amount'`, `'shipments[].items'`) exactly those,
 * so tests can split the set. Either way the runtime checks that a 2xx request of the test really
 * sent each claimed path with a non-empty value.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class CoversApiRequestAttributes
{
    /**
     * @var array<string>
     */
    public array $paths;

    public function __construct(string ...$paths)
    {
        $this->paths = array_values($paths);
    }
}
