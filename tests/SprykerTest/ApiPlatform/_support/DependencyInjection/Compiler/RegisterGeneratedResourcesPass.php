<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\DependencyInjection\Compiler;

use ApiPlatform\Metadata\ApiResource;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass that registers generated API resources with API Platform.
 *
 * During test execution, API resources are generated dynamically and need to be
 * manually registered with API Platform's metadata system. This pass:
 * - Scans configured resource paths for generated PHP classes
 * - Identifies classes with #[ApiResource] attributes
 * - Registers them with API Platform's resource class directories parameter
 *
 * This ensures that dynamically generated resources are discoverable by
 * API Platform's routing and metadata systems.
 */
class RegisterGeneratedResourcesPass implements CompilerPassInterface
{
    /**
     * @param array<string> $resourcePaths
     */
    public function __construct(protected array $resourcePaths)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        if ($this->resourcePaths === []) {
            return;
        }

        foreach ($this->resourcePaths as $resourcePath) {
            if (!is_dir($resourcePath)) {
                continue;
            }

            $this->registerGeneratedResources($container, $resourcePath);
        }
    }

    protected function registerGeneratedResources(ContainerBuilder $container, string $resourcePath): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $resourceClasses = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->extractClassName($file->getPathname());

                if ($className !== null && $this->isApiResource($className)) {
                    $resourceClasses[] = $className;
                }
            }
        }

        if ($resourceClasses !== []) {
            $existingClasses = $container->hasParameter('api_platform.resource_class_directories')
                ? $container->getParameter('api_platform.resource_class_directories')
                : [];

            $container->setParameter(
                'api_platform.resource_class_directories',
                array_merge($existingClasses, [$resourcePath]),
            );
        }
    }

    protected function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if (
            preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch) &&
            preg_match('/class\s+(\w+)/', $content, $classMatch)
        ) {
            return $namespaceMatch[1] . '\\' . $classMatch[1];
        }

        return null;
    }

    protected function isApiResource(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(ApiResource::class);

        return $attributes !== [];
    }
}
