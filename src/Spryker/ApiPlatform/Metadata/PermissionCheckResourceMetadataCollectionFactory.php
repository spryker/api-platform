<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Spryker\ApiPlatform\Security\Permission\OperationPermissionIdentifierResolver;

class PermissionCheckResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /**
     * @var array<string, string>|null
     */
    protected ?array $identifiers = null;

    /**
     * @param list<string> $apiTypes
     */
    public function __construct(
        protected ResourceMetadataCollectionFactoryInterface $decorated,
        protected ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        protected OperationPermissionIdentifierResolver $operationPermissionIdentifierResolver,
        protected array $apiTypes,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);
        $apiType = $this->apiTypes[0] ?? null;

        if ($apiType === null) {
            return $resourceMetadataCollection;
        }

        $identifiers = $this->getIdentifiers($apiType);

        foreach ($resourceMetadataCollection as $resourceIndex => $apiResource) {
            if ($apiResource->getOperations() === null) {
                continue;
            }

            $resourceKey = $resourceClass . OperationPermissionIdentifierResolver::KEY_SEPARATOR . $resourceIndex;
            $resourceMetadataCollection[$resourceIndex] = $this->decorateOperations($apiResource, $resourceKey, $identifiers);
        }

        return $resourceMetadataCollection;
    }

    /**
     * Identifiers are unique across all resource classes, so they are resolved for every class at once.
     *
     * @return array<string, string>
     */
    protected function getIdentifiers(string $apiType): array
    {
        if ($this->identifiers !== null) {
            return $this->identifiers;
        }

        $resourceMetadataCollections = [];

        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            $resourceMetadataCollections[$resourceClass] = $this->decorated->create($resourceClass);
        }

        return $this->identifiers = $this->operationPermissionIdentifierResolver->resolve($apiType, $resourceMetadataCollections);
    }

    /**
     * @param array<string, string> $identifiers
     */
    protected function decorateOperations(ApiResource $apiResource, string $resourceKey, array $identifiers): ApiResource
    {
        $decoratedOperations = [];

        foreach ($apiResource->getOperations() ?? [] as $operationName => $operation) {
            $identifier = $identifiers[$resourceKey . OperationPermissionIdentifierResolver::KEY_SEPARATOR . $operationName] ?? null;

            $decoratedOperations[$operationName] = $identifier === null
                ? $operation
                : $this->addPermissionCheck($operation, $operation->getSecurity() ?? $apiResource->getSecurity(), $identifier);
        }

        return $apiResource->withOperations(new Operations($decoratedOperations));
    }

    protected function addPermissionCheck(HttpOperation $operation, ?string $baselineSecurity, string $identifier): HttpOperation
    {
        $check = sprintf("is_granted('%s')", $identifier);

        return $operation
            ->withSecurity($baselineSecurity !== null && $baselineSecurity !== '' ? sprintf('(%s) and %s', $baselineSecurity, $check) : $check)
            ->withExtraProperties(array_merge($operation->getExtraProperties() ?? [], [
                OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER => $identifier,
                OperationPermissionIdentifierResolver::EXTRA_PROPERTY_BASELINE_SECURITY => $baselineSecurity,
            ]));
    }
}
