<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The unit of include coverage: a read operation's dispatch key paired with a relationship the
 * resource declares under `includes`, which a test has to request with `?include=` and assert.
 */
readonly class IncludeRelationship implements CoverageItem
{
    protected const string KEY_SEPARATOR = '  include ';

    public function __construct(public string $dispatchKey, public string $relationshipName)
    {
    }

    public function key(): string
    {
        return $this->dispatchKey . static::KEY_SEPARATOR . $this->relationshipName;
    }
}
