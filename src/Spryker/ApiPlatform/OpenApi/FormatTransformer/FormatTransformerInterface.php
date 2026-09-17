<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\FormatTransformer;

use ArrayObject;

/**
 * Interface for format-specific transformers that handle OpenAPI schema transformations
 * for different content types (JSON:API, JSON-LD, XML, etc.).
 */
interface FormatTransformerInterface
{
    /**
     * Returns the format suffix this transformer handles.
     *
     * @return string Format suffix without leading dot (e.g., 'jsonapi', 'jsonld', 'xml')
     */
    public function getFormatSuffix(): string;

    /**
     * Returns the mime types this transformer handles.
     *
     * @return array<string> Array of mime types (e.g., ['application/vnd.api+json'])
     */
    public function getMimeTypes(): array;

    /**
     * Enriches the schemas this transformer's format owns with documentation detail the schema
     * factories do not provide, such as example values. Implementations are not required to be
     * idempotent, so the caller invokes this once per transformer per document.
     *
     * Request bodies are not built here. A format that needs its own request-body shape produces it
     * as a schema, by decorating `api_platform.json_schema.backward_compatible_schema_factory` the
     * way {@see \Spryker\ApiPlatform\JsonSchema\JsonApiInputSchemaFactory} does, so that the OpenAPI
     * document and the JSON Schema agree.
     *
     * @param \ArrayObject<string, array<string, mixed>> $schemas
     *
     * @return \ArrayObject<string, array<string, mixed>>
     */
    public function transformSchemas(ArrayObject $schemas): ArrayObject;

    /**
     * Returns the schema reference the request body of the given method must use, for a format whose
     * request body is described by a schema other than the one the operation points at. An
     * implementation whose request-body schemas are already built per operation returns the
     * reference unchanged.
     *
     * @param string $ref Original schema reference (e.g., '#/components/schemas/customers')
     * @param string $method HTTP method (post, put, patch)
     *
     * @return string Schema reference to use for the request body
     */
    public function fixRequestBodyReference(string $ref, string $method): string;
}
