<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validation\Mapper;

class ValidationGroupMapper implements ValidationGroupMapperInterface
{
    /**
     * @var array<string, string>
     */
    protected const array METHOD_TO_GROUP_MAP = [
        'Post' => 'create',
        'Patch' => 'update',
        'Put' => 'replace',
    ];

    public function mapOperationToGroup(string $operation, string $resourceName): string
    {
        $groupSuffix = static::METHOD_TO_GROUP_MAP[$operation] ?? strtolower($operation);

        return sprintf('%s:%s', strtolower($resourceName), $groupSuffix);
    }
}
