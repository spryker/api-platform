<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The relationships one test method asserted through `assertIncludedRelationship()`.
 */
class IncludedRelationshipRecorder
{
    /**
     * @var array<string, true>
     */
    protected array $asserted = [];

    public function record(string $relationshipName): void
    {
        $this->asserted[$relationshipName] = true;
    }

    /**
     * @return array<string>
     */
    public function assertedRelationshipNames(): array
    {
        return array_keys($this->asserted);
    }
}
