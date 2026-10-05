<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Attribute;

/**
 * A situation behind an error response that its status alone does not tell apart. A 403 answers
 * both a missing token and someone else's resource; only the second proves the ownership check.
 */
enum Scenario: string
{
    case FOREIGN_OWNER = 'foreign-owner';
}
