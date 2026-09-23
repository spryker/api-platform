<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;

/**
 * Finds the API Platform operation behind a documented OpenAPI operation. The OpenAPI model does not carry the
 * operation metadata (security expression, provider, extra properties), so both are joined on the `operationId`
 * {@see \ApiPlatform\OpenApi\Factory\OpenApiFactory} writes: the one the resource declares in its `openapi`
 * model, or else the operation name normalised the way the factory does. The key is therefore exact for every
 * documented operation, whatever its `routeName`, `routePrefix` or format suffix.
 */
class OperationMetadataResolver
{
    /**
     * @var array<string, \ApiPlatform\Metadata\HttpOperation>|null
     */
    protected ?array $operationsByOperationId = null;

    public function __construct(
        protected readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        protected readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
    }

    public function resolve(?string $operationId): ?HttpOperation
    {
        if ($operationId === null) {
            return null;
        }

        $this->operationsByOperationId ??= $this->indexOperations();

        return $this->operationsByOperationId[$operationId] ?? null;
    }

    /**
     * @return array<string, \ApiPlatform\Metadata\HttpOperation>
     */
    protected function indexOperations(): array
    {
        $operationsByOperationId = [];

        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $apiResource) {
                $operationsByOperationId += $this->indexResourceOperations($apiResource);
            }
        }

        return $operationsByOperationId;
    }

    /**
     * @return array<string, \ApiPlatform\Metadata\HttpOperation>
     */
    protected function indexResourceOperations(ApiResource $apiResource): array
    {
        $operationsByOperationId = [];

        foreach ($apiResource->getOperations() ?? [] as $operationName => $operation) {
            if (!$this->isDocumented($operation)) {
                continue;
            }

            $operationsByOperationId[$this->resolveOperationId((string)$operationName, $operation)] ??= $operation;
        }

        return $operationsByOperationId;
    }

    /**
     * @see \ApiPlatform\OpenApi\Factory\OpenApiFactory::collectPaths()
     */
    protected function isDocumented(HttpOperation $operation): bool
    {
        if ($operation->getOpenapi() === false) {
            return false;
        }

        return $operation->getUriTemplate() !== null || $operation->getRouteName() !== null;
    }

    protected function resolveOperationId(string $operationName, HttpOperation $operation): string
    {
        $openApiOperation = $operation->getOpenapi();

        if ($openApiOperation instanceof OpenApiOperation && $openApiOperation->getOperationId() !== null) {
            return $openApiOperation->getOperationId();
        }

        return $this->normalizeOperationName($operationName);
    }

    /**
     * @see \ApiPlatform\OpenApi\Serializer\NormalizeOperationNameTrait::normalizeOperationName()
     */
    protected function normalizeOperationName(string $operationName): string
    {
        return (string)preg_replace('/^_/', '', str_replace(['/', '{._format}', '{', '}'], ['', '', '_', ''], $operationName));
    }
}
