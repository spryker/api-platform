<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator\MediaType;

/**
 * Registry for media type formatters used in OpenAPI operation generation.
 *
 * Manages a collection of MediaTypeFormatterInterface implementations and provides
 * lookup functionality by media type. Formatters are automatically registered via
 * dependency injection using tagged services.
 *
 * The registry is used by OpenApiOperationBuilder to:
 * - Find formatters for configured media types
 * - Generate examples for multiple content types in a single operation
 * - Gracefully handle unsupported media types (skip them)
 *
 * Example usage:
 * ```php
 * $registry = new MediaTypeFormatterRegistry([
 *     new JsonApiMediaTypeFormatter(),
 *     new XmlMediaTypeFormatter(),
 * ]);
 *
 * $formatters = $registry->getFormattersForMediaTypes([
 *     'application/vnd.api+json',
 *     'application/xml',
 *     'application/unsupported', // Skipped automatically
 * ]);
 *
 * foreach ($formatters as $mediaType => $formatter) {
 *     $example = $formatter->buildExample($schema, 'Post');
 * }
 * ```
 */
class MediaTypeFormatterRegistry
{
    /**
     * @var array<string, \Spryker\ApiPlatform\Generator\MediaType\MediaTypeFormatterInterface>
     */
    protected array $formattersByMediaType = [];

    /**
     * @param iterable<\Spryker\ApiPlatform\Generator\MediaType\MediaTypeFormatterInterface> $formatters
     */
    public function __construct(iterable $formatters)
    {
        foreach ($formatters as $formatter) {
            $this->formattersByMediaType[$formatter->getMediaType()] = $formatter;
        }
    }

    /**
     * Checks if a formatter exists for the given media type.
     *
     * @param string $mediaType The media type to check
     *
     * @return bool
     */
    public function hasFormatter(string $mediaType): bool
    {
        return isset($this->formattersByMediaType[$mediaType]);
    }

    /**
     * Returns formatters for the given media types.
     * Only returns formatters that exist, skips unsupported media types.
     *
     * This is the primary method used by OpenApiOperationBuilder to generate examples
     * for all configured media types. Unsupported media types are gracefully ignored,
     * allowing the system to work even when not all configured formats have formatters.
     *
     * @param array<string> $mediaTypes The media types to get formatters for
     *
     * @return array<string, \Spryker\ApiPlatform\Generator\MediaType\MediaTypeFormatterInterface>
     */
    public function getFormattersForMediaTypes(array $mediaTypes): array
    {
        $formatters = [];

        foreach ($mediaTypes as $mediaType) {
            if ($this->hasFormatter($mediaType)) {
                $formatters[$mediaType] = $this->formattersByMediaType[$mediaType];
            }
        }

        return $formatters;
    }
}
