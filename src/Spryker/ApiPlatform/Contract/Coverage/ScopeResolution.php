<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

/**
 * The outcome of applying the enforced resource selection to the discovered resources: the
 * must-cover truth, and the existence truth (every resource, which the stale checks measure
 * claims against).
 */
readonly class ScopeResolution
{
    public function __construct(
        public TruthSet $enforcedTruth,
        public TruthSet $existenceTruth,
    ) {
    }
}
