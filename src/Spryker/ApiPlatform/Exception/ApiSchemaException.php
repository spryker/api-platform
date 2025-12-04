<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Exception;

use Exception;

/**
 * Base exception for all API schema-related errors.
 *
 * All exceptions thrown by the API Resource Generator extend this class.
 */
class ApiSchemaException extends Exception
{
}
