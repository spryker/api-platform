<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;

/**
 * `PathItem` exposes one getter and one wither per HTTP method and no generic accessor; this class enumerates every
 * method the model defines, taken from the model's own list so the set the factory can place on a path item is the
 * set this class sees, and lets callers treat the operations of a path as one list.
 */
class PathItemOperationAccessor
{
    protected const string OPERATION_GETTER_FORMAT = 'get%s';

    protected const string OPERATION_SETTER_FORMAT = 'with%s';

    /**
     * @see \ApiPlatform\OpenApi\Factory\OpenApiFactory::collectPaths()
     *
     * @return array<string> Lower-cased, the spelling the OpenAPI document uses as path item keys.
     */
    public function getHttpMethods(): array
    {
        return array_map(strtolower(...), PathItem::$methods);
    }

    /**
     * @return array<string, \ApiPlatform\OpenApi\Model\Operation>
     */
    public function getOperations(PathItem $pathItem): array
    {
        $operations = [];

        foreach ($this->getHttpMethods() as $method) {
            $operation = $this->getOperation($pathItem, $method);

            if ($operation === null) {
                continue;
            }

            $operations[$method] = $operation;
        }

        return $operations;
    }

    public function getOperation(PathItem $pathItem, string $method): ?Operation
    {
        return $pathItem->{sprintf(static::OPERATION_GETTER_FORMAT, ucfirst(strtolower($method)))}();
    }

    public function withOperation(PathItem $pathItem, string $method, Operation $operation): PathItem
    {
        return $pathItem->{sprintf(static::OPERATION_SETTER_FORMAT, ucfirst(strtolower($method)))}($operation);
    }
}
