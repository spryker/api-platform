<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Attribute;

use Attribute;

/**
 * Marks the one test method that round-trips every required response attribute of the operation its
 * {@see CoversApiOperation} names, against the fixture the test itself created. It takes no
 * arguments on purpose: the attribute set is schema truth, read at runtime from the generated
 * `#[ApiResource]` classes, so a list here could only drift from it.
 *
 * An attribute that is legitimately absent in some scenarios opts out in the `.resource.yml` with
 * `responseOptional: true`. Belongs on a method that also carries a success
 * {@see CoversApiOperation}, without which the coverage collection fails.
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class CoversApiRequiredResponseAttributes
{
}
