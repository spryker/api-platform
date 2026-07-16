<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\Result;

/**
 * Result of the canonical-object pre-pass run by {@see \Spryker\ApiPlatform\Generator\CanonicalObjectRegistry}
 * over the resolved `*.object.yml` definitions of an API type before per-resource generation.
 *
 * Carries the generated canonical value-object classes — one per resolved `*.object.yml` definition
 * (its extends-flattened shape), not a cross-resource union — plus the set of known canonical object
 * names. The names let the per-resource generator type an `objectName`-tagged property to the shared
 * canonical class and skip emitting a per-resource companion class for it.
 */
class CanonicalObjectResult
{
    /**
     * @param array<string, string> $canonicalObjectClasses
     * @param array<string, true> $knownCanonicalObjectNames
     */
    public function __construct(
        protected readonly array $canonicalObjectClasses = [],
        protected readonly array $knownCanonicalObjectNames = [],
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getCanonicalObjectClasses(): array
    {
        return $this->canonicalObjectClasses;
    }

    /**
     * @return array<string, true>
     */
    public function getKnownCanonicalObjectNames(): array
    {
        return $this->knownCanonicalObjectNames;
    }
}
