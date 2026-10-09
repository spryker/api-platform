<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The unit of unconstrained-attribute coverage: an attribute a client can write on an input
 * operation whose value has a shape of its own - a date, or one of a closed set such as a store, a
 * currency or a price mode - and which therefore needs a constraint on that operation.
 */
readonly class TypedRequestAttribute implements CoverageItem
{
    protected const string KEY_SEPARATOR = '  ';

    protected const string EXCLUSION_KEY_SEPARATOR = '.';

    public function __construct(public string $resource, public string $dispatchKey, public string $path)
    {
    }

    public function key(): string
    {
        return $this->dispatchKey . static::KEY_SEPARATOR . $this->path;
    }

    /**
     * How `contract_coverage_excluded_unconstrained_attributes` names the attribute: per resource,
     * so one entry opts the attribute out on every operation that accepts it.
     */
    public function exclusionKey(): string
    {
        return $this->resource . static::EXCLUSION_KEY_SEPARATOR . $this->path;
    }
}
