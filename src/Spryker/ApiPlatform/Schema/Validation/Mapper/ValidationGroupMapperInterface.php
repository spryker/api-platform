<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validation\Mapper;

/**
 * Maps HTTP operations to Symfony validation groups.
 */
interface ValidationGroupMapperInterface
{
    public function mapOperationToGroup(string $operation, string $resourceName): string;
}
