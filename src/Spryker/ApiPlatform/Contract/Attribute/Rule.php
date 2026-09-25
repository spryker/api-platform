<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Attribute;

/**
 * The rule identifiers that are *not* simply a Symfony validation constraint's own short name.
 *
 * Only a constraint that breaks that default needs a case here; so far only `Length` does, because
 * one declaration carries two independently testable bounds. The split itself lives in
 * {@see \Spryker\ApiPlatform\Contract\Coverage\ConstraintRuleMapper}.
 */
enum Rule: string
{
    case LENGTH_MAX = 'Length.max';

    case LENGTH_MIN = 'Length.min';
}
