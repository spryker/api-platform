<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Attribute;

use Attribute;

/**
 * Marks a test that requests the named relationships with `?include=` on the success operation its
 * {@see CoversApiOperation} names and asserts each one through `assertIncludedRelationship()`. It
 * takes no "all": every relationship needs a fixture of its own.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class CoversApiIncludes
{
    /**
     * @var array<string>
     */
    public array $relationshipNames;

    public function __construct(string ...$relationshipNames)
    {
        $this->relationshipNames = array_values($relationshipNames);
    }
}
