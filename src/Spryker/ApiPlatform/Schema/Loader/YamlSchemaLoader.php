<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Loader;

use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlSchemaLoader implements SchemaLoaderInterface
{
    /**
     * @var array<string> Supported file extensions
     */
    protected const array SUPPORTED_EXTENSIONS = ['yaml', 'yml'];

    /**
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     *
     * @return array<string, mixed> Normalized schema data
     */
    public function load(SplFileInfo $file): array
    {
        $filePath = $file->getRealPath();

        if ($filePath === false) {
            throw new ApiSchemaValidationException(
                sprintf('Schema file does not exist or is not readable: %s', $file->getPathname()),
                $file->getPathname(),
            );
        }

        try {
            $content = file_get_contents($filePath);

            if ($content === false) {
                throw new ApiSchemaValidationException(
                    sprintf('Unable to read schema file: %s', $filePath),
                    $filePath,
                );
            }

            $data = Yaml::parse($content);

            if (!is_array($data)) {
                throw new ApiSchemaValidationException(
                    'Schema file must contain a YAML object (associative array)',
                    $filePath,
                    1,
                );
            }

            $this->validateStructure($data, $filePath);

            return $data;
        } catch (ParseException $e) {
            throw new ApiSchemaValidationException(
                sprintf('YAML syntax error: %s', $e->getMessage()),
                $filePath,
                $e->getParsedLine(),
                ['original_error' => $e->getMessage()],
                $e,
            );
        }
    }

    public function supports(SplFileInfo $file): bool
    {
        $extension = strtolower($file->getExtension());

        return in_array($extension, static::SUPPORTED_EXTENSIONS, true);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Spryker\ApiPlatform\Exception\ApiSchemaValidationException
     */
    protected function validateStructure(array $data, string $filePath): void
    {
        if (!isset($data['resource'])) {
            throw new ApiSchemaValidationException(
                'Schema file must have a "resource" root key',
                $filePath,
                1,
                ['keys_found' => array_keys($data)],
            );
        }

        if (!is_array($data['resource'])) {
            throw new ApiSchemaValidationException(
                'The "resource" key must contain an object (associative array)',
                $filePath,
                1,
                ['type_found' => gettype($data['resource'])],
            );
        }
    }
}
