<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use ApiPlatform\Metadata\ApiResource;
use ReflectionClass;
use Spryker\ApiPlatform\Exception\ResourceClassIndexException;
use Spryker\ApiPlatform\Utility\ApiTypeNormalizer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiles the generated resource class index into the container parameter
 * `spryker_api_platform.resource_class_index`, grouped by base resource class with the code bucket
 * as inner key ({@see static::CODE_BUCKET_KEY_BASE} for the base resource), so runtime consumers
 * select the variant for the current code bucket with a plain array lookup:
 *
 * 'Generated\Api\Backend\StoresBackendResource' => [
 *     '' => [shortName, className, includedSortPriority], // base resource
 *     'EU' => [...], // EU code bucket variant
 * ]
 */
class ResourceClassIndexPass implements CompilerPassInterface
{
    public const string PARAMETER_RESOURCE_CLASS_INDEX = 'spryker_api_platform.resource_class_index';

    public const string CODE_BUCKET_KEY_BASE = '';

    protected const string PARAMETER_GENERATED_DIR = 'spryker_api_platform.generated_dir';

    protected const string PARAMETER_API_TYPES = 'spryker_api_platform.api_types';

    protected const string GENERATED_CLASS_NAME_PATTERN = 'Generated\\Api\\%s\\%s';

    protected const string CODE_BUCKET_CONSTANT_NAME = 'CODE_BUCKET';

    protected const string EXTRA_PROPERTY_INCLUDED_SORT_PRIORITY = 'includedSortPriority';

    public function process(ContainerBuilder $container): void
    {
        $indexEntries = [];

        if ($this->hasResourceGenerationParameters($container)) {
            $generatedDir = (string)$this->resolveParameter($container, static::PARAMETER_GENERATED_DIR);
            /** @var array<string> $apiTypes */
            $apiTypes = $this->resolveParameter($container, static::PARAMETER_API_TYPES);

            foreach ($apiTypes as $apiType) {
                $indexEntries = array_merge($indexEntries, $this->buildIndexEntries($generatedDir, $apiType));
            }
        }

        $container->setParameter(static::PARAMETER_RESOURCE_CLASS_INDEX, $indexEntries);
    }

    protected function hasResourceGenerationParameters(ContainerBuilder $container): bool
    {
        return $container->hasParameter(static::PARAMETER_GENERATED_DIR)
            && $container->hasParameter(static::PARAMETER_API_TYPES);
    }

    /**
     * Parameter placeholders (%kernel.project_dir%) are not resolved yet at this pass stage.
     */
    protected function resolveParameter(ContainerBuilder $container, string $parameterName): mixed
    {
        return $container->getParameterBag()->resolveValue($container->getParameter($parameterName));
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\ResourceClassIndexException
     *
     * @return array<class-string, array<string, array{shortName: string, className: class-string, includedSortPriority: int|null}>>
     */
    protected function buildIndexEntries(string $generatedDir, string $apiType): array
    {
        $apiType = ApiTypeNormalizer::normalizeForGeneration($apiType);
        $resourceDirectory = sprintf('%s/%s', $generatedDir, $apiType);

        if (!is_dir($resourceDirectory)) {
            return [];
        }

        $indexEntries = [];
        $resourceFilePaths = glob(sprintf('%s/*%sResource.php', $resourceDirectory, $apiType)) ?: [];

        foreach ($resourceFilePaths as $resourceFilePath) {
            $indexEntry = $this->buildIndexEntry($apiType, $resourceFilePath);

            if ($indexEntry === null) {
                continue;
            }

            $baseClassName = $this->resolveBaseClassName($indexEntry['className'], $indexEntry['codeBucket'], $apiType);

            $indexEntries[$baseClassName][$indexEntry['codeBucket'] ?? static::CODE_BUCKET_KEY_BASE] = [
                'shortName' => $indexEntry['shortName'],
                'className' => $indexEntry['className'],
                'includedSortPriority' => $indexEntry['includedSortPriority'],
            ];
        }

        // An empty index silently disables code bucket filtering, so generated files that yield no
        // entries are a broken build — only a missing directory (pre-generation boot) may be empty.
        if ($resourceFilePaths !== [] && $indexEntries === []) {
            throw new ResourceClassIndexException(sprintf(
                'None of the %d generated resource file(s) in "%s" produced a resource class index entry. Check that the generated classes are loadable and carry an #[ApiResource] attribute with a short name.',
                count($resourceFilePaths),
                $resourceDirectory,
            ));
        }

        return $indexEntries;
    }

    /**
     * Distinct resources may share a JSON:API short name (`orders` is exposed by both the Orders
     * and the CustomersOrders resource), so base-to-variant grouping goes by the generated class
     * name: a code bucket variant repeats the base class name with the bucket inserted before the
     * `{ApiType}Resource` suffix (StoresBackendResource → StoresEUBackendResource).
     *
     * @param class-string $className
     *
     * @return class-string
     */
    protected function resolveBaseClassName(string $className, ?string $codeBucket, string $apiType): string
    {
        if ($codeBucket === null || $codeBucket === '') {
            return $className;
        }

        /** @var class-string $baseClassName */
        $baseClassName = str_replace(
            sprintf('%s%sResource', $codeBucket, $apiType),
            sprintf('%sResource', $apiType),
            $className,
        );

        return $baseClassName;
    }

    /**
     * @return array{shortName: string, className: class-string, codeBucket: string|null, includedSortPriority: int|null}|null
     */
    protected function buildIndexEntry(string $apiType, string $resourceFilePath): ?array
    {
        /** @var class-string $className */
        $className = sprintf(static::GENERATED_CLASS_NAME_PATTERN, $apiType, basename($resourceFilePath, '.php'));

        // Loading by explicit path instead of the autoloader: right after generation the composer
        // classmap does not know new classes yet (authoritative classmaps never fall back to scanning).
        if (!class_exists($className, false)) {
            require_once $resourceFilePath;
        }

        if (!class_exists($className, false)) {
            return null;
        }

        $reflectionClass = new ReflectionClass($className);
        $apiResourceAttributes = $reflectionClass->getAttributes(ApiResource::class);

        if ($apiResourceAttributes === []) {
            return null;
        }

        $apiResource = $apiResourceAttributes[0]->newInstance();
        $shortName = $apiResource->getShortName();

        if ($shortName === null) {
            return null;
        }

        $extraProperties = $apiResource->getExtraProperties() ?? [];
        $includedSortPriority = $extraProperties[static::EXTRA_PROPERTY_INCLUDED_SORT_PRIORITY] ?? null;

        $codeBucket = null;

        if ($reflectionClass->hasConstant(static::CODE_BUCKET_CONSTANT_NAME)) {
            $codeBucket = (string)$reflectionClass->getConstant(static::CODE_BUCKET_CONSTANT_NAME);
        }

        return [
            'shortName' => $shortName,
            'className' => $className,
            'codeBucket' => $codeBucket,
            'includedSortPriority' => is_int($includedSortPriority) ? $includedSortPriority : null,
        ];
    }
}
