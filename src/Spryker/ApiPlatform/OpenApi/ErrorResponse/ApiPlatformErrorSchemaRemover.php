<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;

/**
 * Removes API Platform's own error schemas from the components once no operation, no other schema and no kept API
 * Platform error schema references them any more, which is the case as soon as every error response carries
 * {@see GlueApiErrorSchema}. A kept one keeps what it references in turn: `Error.jsonapi` wraps `Error`, so
 * removing `Error` beside a referenced `Error.jsonapi` would leave a dangling reference.
 */
class ApiPlatformErrorSchemaRemover
{
    public function __construct(
        protected readonly PathItemOperationAccessor $pathItemOperationAccessor,
        protected readonly SchemaReferenceResolver $schemaReferenceResolver,
    ) {
    }

    /**
     * Mutates the given component schemas in place, so the caller keeps the instance it registered its own schema in.
     *
     * @param \ArrayObject<string, mixed> $schemas
     */
    public function remove(OpenApi $openApi, ArrayObject $schemas): void
    {
        $referencedSchemaNames = $this->collectReferencedSchemaNames($openApi, $schemas);

        foreach (SchemaReferenceResolver::API_PLATFORM_ERROR_SCHEMA_NAMES as $schemaName) {
            if (!isset($schemas[$schemaName]) || in_array($schemaName, $referencedSchemaNames, true)) {
                continue;
            }

            unset($schemas[$schemaName]);
        }
    }

    /**
     * @param \ArrayObject<string, mixed> $schemas
     *
     * @return array<string>
     */
    protected function collectReferencedSchemaNames(OpenApi $openApi, ArrayObject $schemas): array
    {
        $schemaNames = [];

        foreach ($openApi->getPaths()->getPaths() as $pathItem) {
            foreach ($this->pathItemOperationAccessor->getOperations($pathItem) as $operation) {
                $schemaNames = array_merge($schemaNames, $this->collectOperationSchemaNames($operation));
            }
        }

        foreach ($schemas as $schemaName => $schema) {
            if ($this->schemaReferenceResolver->isApiPlatformErrorSchemaName((string)$schemaName)) {
                continue;
            }

            $schemaNames = array_merge($schemaNames, $this->schemaReferenceResolver->collectNestedSchemaNames($schema));
        }

        return $this->addSchemaNamesReferencedByKeptApiPlatformErrorSchemas(array_values(array_unique($schemaNames)), $schemas);
    }

    /**
     * @param array<string> $schemaNames
     * @param \ArrayObject<string, mixed> $schemas
     *
     * @return array<string>
     */
    protected function addSchemaNamesReferencedByKeptApiPlatformErrorSchemas(array $schemaNames, ArrayObject $schemas): array
    {
        do {
            $countBeforeExpansion = count($schemaNames);

            foreach (SchemaReferenceResolver::API_PLATFORM_ERROR_SCHEMA_NAMES as $schemaName) {
                if (!isset($schemas[$schemaName]) || !in_array($schemaName, $schemaNames, true)) {
                    continue;
                }

                $schemaNames = array_values(array_unique(array_merge(
                    $schemaNames,
                    $this->schemaReferenceResolver->collectNestedSchemaNames($schemas[$schemaName]),
                )));
            }
        } while (count($schemaNames) !== $countBeforeExpansion);

        return $schemaNames;
    }

    /**
     * @return array<string>
     */
    protected function collectOperationSchemaNames(Operation $operation): array
    {
        $mediaTypes = iterator_to_array($operation->getRequestBody()?->getContent() ?? new ArrayObject(), false);

        foreach ($operation->getResponses() ?? [] as $response) {
            $mediaTypes = array_merge($mediaTypes, iterator_to_array($response->getContent() ?? new ArrayObject(), false));
        }

        return array_values(array_filter(array_map($this->schemaReferenceResolver->resolveSchemaName(...), $mediaTypes)));
    }
}
