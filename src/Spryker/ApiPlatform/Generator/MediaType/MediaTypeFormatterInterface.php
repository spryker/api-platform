<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\MediaType;

/**
 * Defines the contract for media type formatters that generate OpenAPI examples.
 *
 * Media type formatters are responsible for building example request bodies for specific
 * content types (media types) in API Platform resources. Each formatter handles one media type
 * and knows how to structure the example data according to that media type's specification.
 *
 * Formatters are automatically discovered via dependency injection tagging and used by the
 * OpenApiOperationBuilder to generate examples for all configured and supported media types.
 *
 * Example usage:
 * ```php
 * class JsonApiMediaTypeFormatter implements MediaTypeFormatterInterface
 * {
 *     public function getMediaType(): string
 *     {
 *         return 'application/vnd.api+json';
 *     }
 *
 *     public function buildExample(array $parsedSchema, string $operationType): array
 *     {
 *         return [
 *             'data' => [
 *                 'type' => $parsedSchema['shortName'],
 *                 'attributes' => [...],
 *             ],
 *         ];
 *     }
 *
 *     public function formatExampleAsCode(array $example): string
 *     {
 *         return var_export($example, true);
 *     }
 * }
 * ```
 */
interface MediaTypeFormatterInterface
{
    /**
     * Returns the media type this formatter supports.
     *
     * This should be the primary MIME type for the format (e.g., 'application/vnd.api+json',
     * 'application/xml', 'application/json').
     */
    public function getMediaType(): string;

    /**
     * Builds the example structure for this media type.
     *
     * Takes a parsed resource schema and operation type, and returns an array structure
     * that represents an example request body for this media type. The structure should
     * follow the conventions of the media type specification.
     *
     * The method should:
     * - Filter out identifier properties (not writable)
     * - Filter out read-only properties for write operations (Post, Patch, Put)
     * - Include only properties with example values from openapiContext
     * - Structure the data according to the media type specification
     *
     * @param array<string, mixed> $parsedSchema The complete parsed resource schema
     * @param string $operationType The operation type (Post, Patch, Put, etc.)
     *
     * @return array<string, mixed> The example structure for this media type
     */
    public function buildExample(array $parsedSchema, string $operationType): array;

    /**
     * Formats the example as a PHP code string for code generation.
     *
     * Takes the example array returned by buildExample() and converts it to a PHP code
     * string that can be embedded in the generated resource class.
     *
     * Common implementations use var_export() or custom formatting logic.
     *
     * @param array<string, mixed> $example The example structure to format
     *
     * @return string PHP code representation of the example
     */
    public function formatExampleAsCode(array $example): string;
}
