<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Object\Loader;

use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface;
use Spryker\ApiPlatform\Schema\Validation\Loader\ValidationSchemaLoaderInterface;

/**
 * Loads and normalizes a single *.object.yml canonical-object schema file.
 *
 * Uses ValidationSchemaLoader for raw YAML parsing (object files use the `object:` root key,
 * not the `resource:` key expected by YamlSchemaLoader) and delegates property-type normalization
 * and layer detection to SchemaParser via its public seam methods, so the type-alias map and
 * layer logic are never duplicated.
 */
class ObjectSchemaLoader implements ObjectSchemaLoaderInterface
{
    /**
     * @var list<string>
     */
    protected const array VALID_LAYERS = ['core', 'feature', 'project'];

    public function __construct(
        protected readonly ValidationSchemaLoaderInterface $loader,
        protected readonly SchemaParserInterface $schemaParser,
    ) {
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaGenerationException
     *
     * @return array{name: string, extends: string|null, omit: array<int, string>, properties: array<string, array<string, mixed>>, layer: string, sourceFile: string}
     */
    public function load(SplFileInfo $file, ?string $layerOverride = null): array
    {
        $raw = $this->loader->load($file);

        $filePath = $file->getRealPath() ?: $file->getPathname();

        if ($layerOverride !== null && !in_array($layerOverride, static::VALID_LAYERS, true)) {
            throw new ApiSchemaGenerationException(
                sprintf(
                    'Invalid layer "%s" for object schema file "%s". Allowed layers are: %s.',
                    $layerOverride,
                    $filePath,
                    implode(', ', static::VALID_LAYERS),
                ),
            );
        }

        if (!is_array($raw['object'] ?? null) || !is_string($raw['object']['name'] ?? null) || $raw['object']['name'] === '') {
            throw new ApiSchemaValidationException(
                sprintf('Object schema file "%s" must have an "object" key with a non-empty "name".', $filePath),
                $filePath,
            );
        }

        /** @var array<string, mixed> $object */
        $object = $raw['object'];

        /** @var array<string, mixed> $rawProperties */
        $rawProperties = is_array($object['properties'] ?? null) ? $object['properties'] : [];

        /** @var array<string> $omit */
        $omit = is_array($object['omit'] ?? null) ? array_values(array_filter($object['omit'], 'is_string')) : [];

        return [
            'name' => $object['name'],
            'extends' => is_string($object['extends'] ?? null) ? $object['extends'] : null,
            'omit' => $omit,
            'properties' => $this->schemaParser->normalizeObjectProperties($rawProperties, $filePath),
            'layer' => $layerOverride ?? $this->schemaParser->detectLayer($filePath),
            'sourceFile' => $filePath,
        ];
    }

    public function detectLayer(string $filePath): string
    {
        return $this->schemaParser->detectLayer($filePath);
    }
}
