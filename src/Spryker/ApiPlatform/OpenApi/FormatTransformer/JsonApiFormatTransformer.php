<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\FormatTransformer;

use ArrayObject;

/**
 * Fills in the `type` example of the JSON:API response schemas, so a reader can recognise the
 * resource in a payload.
 *
 * Request bodies used to be rebuilt here, out of the finished document. They are now produced by
 * {@see \Spryker\ApiPlatform\JsonSchema\JsonApiInputSchemaFactory}, where the operation, the format
 * and the serialization groups are all still known — a document made of `$ref` strings cannot tell a
 * request body from any other schema.
 */
class JsonApiFormatTransformer implements FormatTransformerInterface
{
    protected const string FORMAT_SUFFIX = 'jsonapi';

    /**
     * Both media types are registered under the `jsonapi` format (`api_platform.formats`), so a
     * request sent as `application/json` is deserialized by the JSON:API denormalizer. There is no
     * plain-JSON format in this application.
     *
     * @var array<string>
     */
    protected const array MIME_TYPES = [
        'application/vnd.api+json',
        'application/json',
    ];

    public function getFormatSuffix(): string
    {
        return static::FORMAT_SUFFIX;
    }

    public function getMimeTypes(): array
    {
        return static::MIME_TYPES;
    }

    /**
     * @param \ArrayObject<string, array<string, mixed>> $schemas
     *
     * @return \ArrayObject<string, array<string, mixed>>
     */
    public function transformSchemas(ArrayObject $schemas): ArrayObject
    {
        foreach ($schemas as $schemaName => $schemaDefinition) {
            $schemaName = str_replace('_noid', '', $schemaName);

            if (!$this->isJsonApiSchema($schemaName)) {
                continue;
            }

            $schemas[$schemaName] = $this->addExampleValues($schemaDefinition, $this->extractResourceShortName($schemaName));
        }

        return $schemas;
    }

    /**
     * The request body already references the schema built for its operation, so there is nothing to
     * correct.
     */
    public function fixRequestBodyReference(string $ref, string $method): string
    {
        return $ref;
    }

    protected function isJsonApiSchema(string $schemaName): bool
    {
        return str_ends_with($schemaName, sprintf('.%s', static::FORMAT_SUFFIX));
    }

    /**
     * Example: "customers.jsonapi" -> "customers"
     */
    protected function extractResourceShortName(string $schemaName): string
    {
        return str_replace(sprintf('.%s', static::FORMAT_SUFFIX), '', $schemaName);
    }

    /**
     * @param \ArrayObject<string, mixed>|array<string, mixed> $schemaDefinition
     *
     * @return array<string, mixed>
     */
    protected function addExampleValues(array|ArrayObject $schemaDefinition, string $resourceShortName): array
    {
        $schemaDefinition = $schemaDefinition instanceof ArrayObject ? $schemaDefinition->getArrayCopy() : $schemaDefinition;

        if (!isset($schemaDefinition['properties']['data']['properties'])) {
            return $schemaDefinition;
        }

        if (isset($schemaDefinition['properties']['data']['properties']['type']) && is_array($schemaDefinition['properties']['data']['properties']['type'])) {
            $schemaDefinition['properties']['data']['properties']['type']['example'] = $resourceShortName;
        }

        return $schemaDefinition;
    }
}
