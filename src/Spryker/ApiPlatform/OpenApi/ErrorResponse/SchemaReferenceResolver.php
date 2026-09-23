<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\OpenApi\Model\MediaType;
use ArrayObject;
use Traversable;

/**
 * Reads and writes the `$ref` schema references of the OpenAPI model, and knows which component schemas are API
 * Platform's own error schemas (`Error`, `ConstraintViolation` and their JSON:API variants).
 */
class SchemaReferenceResolver
{
    /**
     * @var array<string>
     */
    public const array API_PLATFORM_ERROR_SCHEMA_NAMES = ['Error', 'Error.jsonapi', 'ConstraintViolation', 'ConstraintViolation.jsonapi'];

    public const string SCHEMA_REFERENCE_PREFIX = '#/components/schemas/';

    protected const string SCHEMA_KEY_REFERENCE = '$ref';

    public function resolveSchemaName(mixed $mediaType): ?string
    {
        if (!$mediaType instanceof MediaType || $mediaType->getSchema() === null) {
            return null;
        }

        return $this->resolveReferencedSchemaName($mediaType->getSchema()[static::SCHEMA_KEY_REFERENCE] ?? null);
    }

    public function isApiPlatformErrorSchemaName(?string $schemaName): bool
    {
        return in_array($schemaName, static::API_PLATFORM_ERROR_SCHEMA_NAMES, true);
    }

    public function isApiPlatformErrorSchema(mixed $mediaType): bool
    {
        return $this->isApiPlatformErrorSchemaName($this->resolveSchemaName($mediaType));
    }

    /**
     * @return \ArrayObject<string, string>
     */
    public function createSchemaReference(string $reference): ArrayObject
    {
        return new ArrayObject([static::SCHEMA_KEY_REFERENCE => $reference]);
    }

    /**
     * @return array<string>
     */
    public function collectNestedSchemaNames(mixed $schema): array
    {
        if ($schema instanceof Traversable) {
            $schema = iterator_to_array($schema);
        }

        if (!is_array($schema)) {
            return [];
        }

        $schemaNames = [];

        foreach ($schema as $key => $value) {
            if ($key === static::SCHEMA_KEY_REFERENCE) {
                $schemaNames[] = $this->resolveReferencedSchemaName($value);

                continue;
            }

            $schemaNames = array_merge($schemaNames, $this->collectNestedSchemaNames($value));
        }

        return array_values(array_filter($schemaNames));
    }

    protected function resolveReferencedSchemaName(mixed $reference): ?string
    {
        if (!is_string($reference) || !str_starts_with($reference, static::SCHEMA_REFERENCE_PREFIX)) {
            return null;
        }

        return substr($reference, strlen(static::SCHEMA_REFERENCE_PREFIX));
    }
}
