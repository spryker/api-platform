<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Security\Permission;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

class OperationPermissionIdentifierResolver
{
    public const string EXTRA_PROPERTY_PERMISSION_IDENTIFIER = 'spryker_permission_identifier';

    public const string EXTRA_PROPERTY_BASELINE_SECURITY = 'spryker_permission_baseline_security';

    public const string EXTRA_PROPERTY_OWNERSHIP = 'securityOwnership';

    protected const string IDENTIFIER_PREFIX = 'api';

    protected const string GENERATED_NAME_PREFIX = '_api_';

    public const string KEY_SEPARATOR = '|';

    /**
     * Operations whose canonical URI template is `/<resource>`; every other operation's is `/<resource>/{<placeholder>}`.
     *
     * @var list<class-string>
     */
    protected const array COLLECTION_OPERATION_CLASSES = [GetCollection::class, Post::class];

    /**
     * @var array<class-string, string>
     */
    protected const array OPERATION_KINDS = [
        GetCollection::class => 'get-collection',
        Get::class => 'get',
        Post::class => 'post',
        Put::class => 'put',
        Patch::class => 'patch',
        Delete::class => 'delete',
    ];

    /**
     * An identifier depends only on its own operation, so adding a resource never renames an existing identifier:
     * an operation on its canonical URI template (`/orders`, `/orders/{id}`) gets `api.<api type>.<resource>.<kind>`,
     * any other (`/customers/{customerReference}/orders`) gets its name or URI template appended.
     * Operations with the same method and URI template are one endpoint and share their identifier.
     *
     * @param array<string, \ApiPlatform\Metadata\Resource\ResourceMetadataCollection> $resourceMetadataCollections Keyed by resource class.
     *
     * @return array<string, string> Keyed by `<resource class>|<resource index>|<operation name>`.
     */
    public function resolve(string $apiType, array $resourceMetadataCollections): array
    {
        $identifiers = [];

        foreach ($this->collectCandidates($apiType, $resourceMetadataCollections) as $base => $operations) {
            $identifiers += $this->resolveUniqueIdentifiers($base, $operations);
        }

        return $identifiers;
    }

    /**
     * @param array<string, \ApiPlatform\Metadata\Resource\ResourceMetadataCollection> $resourceMetadataCollections
     *
     * @return array<string, array<string, \ApiPlatform\Metadata\HttpOperation>>
     */
    protected function collectCandidates(string $apiType, array $resourceMetadataCollections): array
    {
        $candidates = [];

        foreach ($resourceMetadataCollections as $resourceClass => $resourceMetadataCollection) {
            foreach ($resourceMetadataCollection as $resourceIndex => $apiResource) {
                foreach ($apiResource->getOperations() ?? [] as $operationName => $operation) {
                    $base = implode('.', [
                        static::IDENTIFIER_PREFIX,
                        $this->toSegment($apiType),
                        $this->toSegment((string)($operation->getShortName() ?? $apiResource->getShortName())),
                        $this->resolveKind($operation),
                    ]);
                    $key = implode(static::KEY_SEPARATOR, [$resourceClass, $resourceIndex, $operationName]);
                    $candidates[$base][$key] = $operation;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, \ApiPlatform\Metadata\HttpOperation> $operations
     *
     * @return array<string, string>
     */
    protected function resolveUniqueIdentifiers(string $base, array $operations): array
    {
        $endpoints = [];

        foreach ($operations as $key => $operation) {
            $endpoints[$this->resolveEndpoint($operation)][$key] = $operation;
        }

        $identifiers = [];

        foreach ($endpoints as $endpointOperations) {
            $operation = reset($endpointOperations);
            $identifier = $this->isCanonicalUriTemplate($operation) ? $base : $base . '.' . $this->resolveDisambiguator($operation);
            $identifiers += array_fill_keys(array_keys($endpointOperations), $identifier);
        }

        return $identifiers;
    }

    protected function isCanonicalUriTemplate(HttpOperation $operation): bool
    {
        $segments = explode('/', trim($this->normalizeUriTemplate((string)$operation->getUriTemplate()), '/'));
        $resourceSegment = $this->toSegment((string)$operation->getShortName());

        if ($resourceSegment === '' || $this->toSegment(array_shift($segments)) !== $resourceSegment) {
            return false;
        }

        foreach (static::COLLECTION_OPERATION_CLASSES as $collectionOperationClass) {
            if ($operation instanceof $collectionOperationClass) {
                return $segments === [];
            }
        }

        return count($segments) === 1 && preg_match('/^\{[^{}\/]+\}$/', $segments[0]) === 1;
    }

    protected function resolveEndpoint(HttpOperation $operation): string
    {
        return $operation->getMethod() . ' ' . $this->normalizeUriTemplate((string)$operation->getUriTemplate());
    }

    protected function normalizeUriTemplate(string $uriTemplate): string
    {
        return str_replace('{._format}', '', $uriTemplate);
    }

    protected function resolveKind(HttpOperation $operation): string
    {
        foreach (static::OPERATION_KINDS as $class => $kind) {
            if ($operation instanceof $class) {
                return $kind;
            }
        }

        return strtolower($operation->getMethod());
    }

    protected function resolveDisambiguator(HttpOperation $operation): string
    {
        $name = (string)$operation->getName();

        if ($name !== '' && !str_starts_with($name, static::GENERATED_NAME_PREFIX)) {
            return $this->toSegment($name);
        }

        $uriTemplate = $this->normalizeUriTemplate((string)$operation->getUriTemplate());

        return $this->toSegment(str_replace(['{', '}'], '', $uriTemplate));
    }

    protected function toSegment(string $value): string
    {
        $kebab = strtolower((string)preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $value));
        $kebab = (string)preg_replace('/[^a-z0-9]+/', '-', $kebab);

        return trim($kebab, '-');
    }
}
