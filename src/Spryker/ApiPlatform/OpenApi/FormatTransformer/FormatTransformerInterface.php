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
     * Transforms schemas by creating input variants, adding examples, etc.
     *
     * @param \ArrayObject<string, array<string, mixed>> $schemas
     *
     * @return \ArrayObject<string, array<string, mixed>>
     */
    public function transformSchemas(ArrayObject $schemas): ArrayObject;

    /**
     * Fixes request body schema reference based on HTTP method.
     *
     * For POST: Returns reference to -input schema variant
     * For PUT/PATCH: Returns reference to regular schema
     *
     * @param string $ref Original schema reference (e.g., '#/components/schemas/customers')
     * @param string $method HTTP method (post, put, patch)
     *
     * @return string Fixed schema reference
     */
    public function fixRequestBodyReference(string $ref, string $method): string;
}
