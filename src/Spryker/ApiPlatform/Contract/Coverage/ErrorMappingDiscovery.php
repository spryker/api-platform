<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Coverage;

use ReflectionClass;
use ReflectionMethod;

/**
 * Lists the error mappings a module's Glue config seems to carry that no resource registers. The
 * mappings share no interface and their names vary, so this is a name-based guess: a warning to
 * review, never a failure, and the registration in the `.resource.yml` stays the contract.
 */
class ErrorMappingDiscovery
{
    protected const string PATTERN_MODULE_ROOT = '#(?:^|/)src/(?<organization>[A-Za-z]+)/(?<module>[A-Za-z]+)/resources/api/#';

    /**
     * A module installed by Composer: `vendor/spryker/carts-rest-api/resources/api/`.
     */
    protected const string PATTERN_PACKAGE_ROOT = '#(?:^|/)vendor/(?<organization>[a-z0-9-]+)/(?<module>[a-z0-9-]+)/resources/api/#';

    protected const string PACKAGE_NAME_SEPARATOR = '-';

    protected const string PATTERN_MAPPING_METHOD = '/^get\w*Error\w*Mapping$/';

    protected const string CONFIG_CLASS_TEMPLATE = '%1$s\\Glue\\%2$s\\%2$sConfig';

    protected const string SOURCE_TEMPLATE = '%s::%s';

    /**
     * @var non-empty-string
     */
    protected const string SOURCE_SEPARATOR = '::';

    public function __construct(protected ErrorMappingResolver $errorMappingResolver)
    {
    }

    /**
     * @param array<string> $schemaFiles The schema files of the enforced resources.
     * @param array<string> $registeredSources Every `Class::method` a resource registers.
     *
     * @return array<string> `Class::method` of each mapping nobody registers, sorted.
     */
    public function unregisteredMappings(array $schemaFiles, array $registeredSources): array
    {
        $registered = [];
        foreach ($registeredSources as $registeredSource) {
            $registered[$registeredSource] = true;
            [$class, $method] = array_pad(explode(static::SOURCE_SEPARATOR, $registeredSource, 2), 2, '');
            $registered[sprintf(static::SOURCE_TEMPLATE, $this->errorMappingResolver->resolveProjectOverride($class), $method)] = true;
        }

        $unregistered = [];
        foreach ($this->configClasses($schemaFiles) as $configClass) {
            foreach ($this->mappingMethods($configClass) as $method) {
                $source = sprintf(static::SOURCE_TEMPLATE, $configClass, $method);
                $overrideSource = sprintf(static::SOURCE_TEMPLATE, $this->errorMappingResolver->resolveProjectOverride($configClass), $method);

                if (!isset($registered[$source]) && !isset($registered[$overrideSource])) {
                    $unregistered[$source] = true;
                }
            }
        }

        $sources = array_keys($unregistered);
        sort($sources);

        return $sources;
    }

    /**
     * @param array<string> $schemaFiles
     *
     * @return array<class-string>
     */
    protected function configClasses(array $schemaFiles): array
    {
        $configClasses = [];

        foreach ($schemaFiles as $schemaFile) {
            $configClass = $this->resolveConfigClass($schemaFile);
            if ($configClass !== null && class_exists($configClass)) {
                $configClasses[$configClass] = $configClass;
            }
        }

        return array_values($configClasses);
    }

    protected function resolveConfigClass(string $schemaFile): ?string
    {
        if (preg_match(static::PATTERN_MODULE_ROOT, $schemaFile, $matches)) {
            return sprintf(static::CONFIG_CLASS_TEMPLATE, $matches['organization'], $matches['module']);
        }

        if (preg_match(static::PATTERN_PACKAGE_ROOT, $schemaFile, $matches)) {
            return sprintf(static::CONFIG_CLASS_TEMPLATE, $this->convertToPascalCase($matches['organization']), $this->convertToPascalCase($matches['module']));
        }

        return null;
    }

    protected function convertToPascalCase(string $packageName): string
    {
        return str_replace(static::PACKAGE_NAME_SEPARATOR, '', ucwords($packageName, static::PACKAGE_NAME_SEPARATOR));
    }

    /**
     * @param class-string $configClass
     *
     * @return array<string>
     */
    protected function mappingMethods(string $configClass): array
    {
        $methods = [];

        foreach ((new ReflectionClass($configClass))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (preg_match(static::PATTERN_MAPPING_METHOD, $method->getName())) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }
}
