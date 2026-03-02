<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use InvalidArgumentException;
use Laminas\Filter\FilterChain;
use Laminas\Filter\StringToLower;
use Laminas\Filter\Word\CamelCaseToDash;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Builds the api_platform.relationships container parameter from resource schema files.
 *
 * This compiler pass scans all resource schema files (*.resource.yml/yaml) and extracts
 * the 'includes' configuration to build a centralized relationship registry stored as
 * the 'api_platform.relationships' container parameter.
 */
class RelationshipConfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$this->hasRequiredParameters($container)) {
            return;
        }

        $apiTypes = $container->getParameter('spryker_api_platform.api_types');

        if (!is_array($apiTypes) || $apiTypes === []) {
            return;
        }

        $sourceDirectories = $container->getParameter('spryker_api_platform.source_directories');

        if (!is_array($sourceDirectories)) {
            return;
        }

        $relationships = [];

        foreach ($apiTypes as $apiType) {
            $schemaFiles = $this->findSchemaFiles($sourceDirectories, $apiType);

            foreach ($schemaFiles as $schemaFile) {
                $relationshipConfigs = $this->extractRelationshipsFromSchema($schemaFile);

                $relationships = array_merge($relationships, $relationshipConfigs);
            }
        }

        $container->setParameter('api_platform.relationships', $relationships);
    }

    protected function hasRequiredParameters(ContainerBuilder $container): bool
    {
        return $container->hasParameter('spryker_api_platform.api_types')
            && $container->hasParameter('spryker_api_platform.source_directories');
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<\SplFileInfo>
     */
    protected function findSchemaFiles(array $sourceDirectories, string $apiType): array
    {
        $apiType = strtolower($apiType);
        $searchDirectories = $this->getSearchDirectories($sourceDirectories, $apiType);

        if ($searchDirectories === []) {
            return [];
        }

        $schemaFiles = [];

        try {
            $finder = new Finder();
            $finder->files()
                ->in($searchDirectories)
                ->name('*.resource.yml')
                ->name('*.resource.yaml')
                ->sortByName();

            foreach ($finder as $file) {
                $schemaFiles[] = $file;
            }
        } catch (InvalidArgumentException $e) {
            return [];
        }

        return $schemaFiles;
    }

    /**
     * @param array<string> $sourceDirectories
     *
     * @return array<string>
     */
    protected function getSearchDirectories(array $sourceDirectories, string $apiType): array
    {
        $searchDirectories = [];

        foreach ($sourceDirectories as $baseDir) {
            $baseDir = rtrim($baseDir, '/');

            $matchingDirs = glob(sprintf('%s/*/resources/api/%s', $baseDir, $apiType), GLOB_ONLYDIR);

            if ($matchingDirs === false || $matchingDirs === []) {
                continue;
            }

            foreach ($matchingDirs as $dir) {
                if (is_dir($dir) && is_readable($dir)) {
                    $searchDirectories[] = $dir;
                }
            }
        }

        return $searchDirectories;
    }

    /**
     * @param \SplFileInfo $schemaFile
     *
     * @return array<string, array<string, mixed>>
     */
    protected function extractRelationshipsFromSchema(SplFileInfo $schemaFile): array
    {
        $filePath = $schemaFile->getRealPath();

        if ($filePath === false) {
            return [];
        }

        try {
            $schema = Yaml::parseFile($filePath);
        } catch (Throwable $e) {
            return [];
        }

        if (!isset($schema['resource']) || !is_array($schema['resource'])) {
            return [];
        }

        $resource = $schema['resource'];

        $resourceShortName = $resource['shortName'] ?? $resource['name'] ?? null;

        if ($resourceShortName === null) {
            return [];
        }

        $includes = $resource['includes'] ?? [];

        if (!is_array($includes) || $includes === []) {
            return [];
        }

        $relationships = [];

        foreach ($includes as $include) {
            if (!is_array($include)) {
                continue;
            }

            $relationshipName = $include['relationshipName'] ?? null;
            $targetResource = $include['targetResource'] ?? null;

            if ($relationshipName === null || $targetResource === null) {
                continue;
            }

            $uriVariableMappings = $include['uriVariableMappings'] ?? [];

            if (!is_array($uriVariableMappings)) {
                $uriVariableMappings = [];
            }

            $targetProviderServiceId = $this->findProviderForResource($targetResource, dirname($filePath));

            if ($targetProviderServiceId === null) {
                continue;
            }

            $key = sprintf('%s.%s', $resourceShortName, $relationshipName);

            $relationships[$key] = [
                'relationship_name' => $relationshipName,
                'target_resource_type' => $this->convertToKebabCase($targetResource),
                'provider_service_id' => $targetProviderServiceId,
                'uri_variable_mappings' => $uriVariableMappings,
            ];
        }

        return $relationships;
    }

    protected function findProviderForResource(string $targetResource, string $currentSchemaDir): ?string
    {
        $targetFileName = $this->convertToKebabCase($targetResource) . '.resource.yml';
        $targetFilePath = sprintf('%s/%s', $currentSchemaDir, $targetFileName);

        if (!file_exists($targetFilePath)) {
            $targetFilePath = sprintf('%s/%s', $currentSchemaDir, str_replace('.yml', '.yaml', $targetFileName));
        }

        if (!file_exists($targetFilePath)) {
            return null;
        }

        try {
            $targetSchema = Yaml::parseFile($targetFilePath);
        } catch (Throwable $e) {
            return null;
        }

        if (!isset($targetSchema['resource']['provider'])) {
            return null;
        }

        return $targetSchema['resource']['provider'];
    }

    protected function convertToKebabCase(string $string): string
    {
        $filterChain = (new FilterChain())
            ->attach(new CamelCaseToDash())
            ->attach(new StringToLower());

        return $filterChain->filter($string);
    }
}
