<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\OpenApi\Model\Operation;
use ArrayObject;

/**
 * Attribute names of a write operation's JSON:API request schema, the required ones first, so the validation
 * example can name the attribute the runtime reports missing first.
 */
class RequestAttributesResolver
{
    protected const string SCHEMA_KEY_PROPERTIES = 'properties';

    protected const string SCHEMA_KEY_REQUIRED = 'required';

    protected const string SCHEMA_PROPERTY_DATA = 'data';

    protected const string SCHEMA_PROPERTY_ATTRIBUTES = 'attributes';

    public function __construct(protected readonly SchemaReferenceResolver $schemaReferenceResolver)
    {
    }

    /**
     * @param \ArrayObject<string, mixed> $schemas
     *
     * @return array<string>
     */
    public function resolve(Operation $operation, ArrayObject $schemas): array
    {
        $attributesSchema = $this->resolveRequestAttributesSchema($operation, $schemas);
        $requiredAttributes = $this->toArray($attributesSchema[static::SCHEMA_KEY_REQUIRED] ?? null);
        $allAttributes = array_keys($this->toArray($attributesSchema[static::SCHEMA_KEY_PROPERTIES] ?? null));

        $attributes = array_merge($requiredAttributes, $allAttributes);

        return array_values(array_unique(array_map(static fn (mixed $attribute): string => (string)$attribute, $attributes)));
    }

    /**
     * @param \ArrayObject<string, mixed> $schemas
     *
     * @return array<string, mixed>
     */
    protected function resolveRequestAttributesSchema(Operation $operation, ArrayObject $schemas): array
    {
        foreach ($operation->getRequestBody()?->getContent() ?? [] as $mediaType) {
            $schemaName = $this->schemaReferenceResolver->resolveSchemaName($mediaType);

            if ($schemaName === null || !isset($schemas[$schemaName])) {
                continue;
            }

            $dataSchema = $this->toArray($this->toArray($this->toArray($schemas[$schemaName])[static::SCHEMA_KEY_PROPERTIES] ?? null)[static::SCHEMA_PROPERTY_DATA] ?? null);
            $attributesSchema = $this->toArray($this->toArray($dataSchema[static::SCHEMA_KEY_PROPERTIES] ?? null)[static::SCHEMA_PROPERTY_ATTRIBUTES] ?? null);

            if ($attributesSchema !== []) {
                return $attributesSchema;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(mixed $value): array
    {
        if ($value instanceof ArrayObject) {
            return $value->getArrayCopy();
        }

        return is_array($value) ? $value : [];
    }
}
