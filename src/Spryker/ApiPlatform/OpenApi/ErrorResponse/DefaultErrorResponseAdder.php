<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Adds the error responses every operation can answer without any resource YAML declaring them: the 400 of a
 * malformed `filter` parameter on every operation, 401 and 403 on a protected one, 404 on an item operation and the
 * `default` response for the statuses the framework answers on every path. Declared responses are kept. A public
 * operation gets an empty security requirement, so Swagger UI stops locking it.
 */
class DefaultErrorResponseAdder
{
    protected const string HTTP_METHOD_POST = 'POST';

    public function __construct(protected readonly ErrorResponseBuilder $errorResponseBuilder)
    {
    }

    public function add(Operation $operation, HttpOperation $httpOperation): Operation
    {
        $isProtected = $this->errorResponseBuilder->isProtectedOperation($httpOperation);
        $statuses = array_merge(
            [HttpResponse::HTTP_BAD_REQUEST],
            $isProtected ? [HttpResponse::HTTP_UNAUTHORIZED, HttpResponse::HTTP_FORBIDDEN] : [],
            $this->isItemOperation($httpOperation) ? [HttpResponse::HTTP_NOT_FOUND] : [],
        );
        $operation = $isProtected ? $operation : $operation->withSecurity([]);

        return $this->ensureDefaultResponse($this->ensureResponses($operation, $statuses, $httpOperation), $httpOperation);
    }

    /**
     * @param array<int> $statuses
     */
    protected function ensureResponses(Operation $operation, array $statuses, HttpOperation $httpOperation): Operation
    {
        $responses = $operation->getResponses() ?? [];

        foreach ($statuses as $status) {
            if (isset($responses[$status])) {
                continue;
            }

            $operation = $operation->withResponse($status, new Response($this->errorResponseBuilder->buildDescription($status, $httpOperation)));
        }

        return $operation;
    }

    protected function ensureDefaultResponse(Operation $operation, HttpOperation $httpOperation): Operation
    {
        $responses = $operation->getResponses() ?? [];

        if (isset($responses[ErrorResponseDocumenter::RESPONSE_KEY_DEFAULT])) {
            return $operation;
        }

        return $operation->withResponse(
            ErrorResponseDocumenter::RESPONSE_KEY_DEFAULT,
            new Response($this->errorResponseBuilder->buildDefaultDescription($httpOperation)),
        );
    }

    protected function isItemOperation(HttpOperation $httpOperation): bool
    {
        return !$httpOperation instanceof CollectionOperationInterface
            && strtoupper((string)$httpOperation->getMethod()) !== static::HTTP_METHOD_POST;
    }
}
