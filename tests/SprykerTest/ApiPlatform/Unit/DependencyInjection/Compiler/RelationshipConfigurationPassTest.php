<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection\Compiler;

use Codeception\Test\Unit;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spryker\ApiPlatform\DependencyInjection\Compiler\RelationshipConfigurationPass;
use Spryker\ApiPlatform\Relationship\ApiPlatformRelationshipResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;
use stdClass;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group Compiler
 * @group RelationshipConfigurationPassTest
 * Add your own group annotations below this line
 */
class RelationshipConfigurationPassTest extends Unit
{
    protected const string EXISTING_RESOLVER_CLASS = stdClass::class;

    protected const string MISSING_RESOLVER_CLASS = 'App\\Missing\\DoesNotExistRelationshipResolver';

    protected const string API_TYPE = 'storefront';

    protected ApiUnitTester $tester;

    protected string $tmpDir = '';

    protected function _before(): void
    {
        $this->tmpDir = sprintf('%s/relationship-configuration-pass-%s', sys_get_temp_dir(), uniqid());
        $schemaDir = sprintf('%s/FakeModule/resources/api/%s', $this->tmpDir, static::API_TYPE);
        mkdir($schemaDir, 0777, true);
    }

    protected function _after(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testGivenExistingResolverClassWhenProcessingThenRelationshipIsKeptAndReferenced(): void
    {
        // Arrange
        $this->writeSchema('foo.resource.yml', [
            'resource' => [
                'name' => 'Foo',
                'shortName' => 'foo',
                'provider' => 'App\\Provider\\FooProvider',
                'includes' => [
                    [
                        'relationshipName' => 'bar',
                        'resolverClass' => static::EXISTING_RESOLVER_CLASS,
                    ],
                ],
            ],
        ]);

        $container = $this->buildContainer();

        $pass = new RelationshipConfigurationPass();

        // Act
        $pass->process($container);

        // Assert
        $relationships = $container->getParameter('api_platform.relationships');
        $this->assertArrayHasKey('foo.bar', $relationships);
        $this->assertSame(static::EXISTING_RESOLVER_CLASS, $relationships['foo.bar']['resolver_class']);

        $this->assertTrue($container->hasDefinition(static::EXISTING_RESOLVER_CLASS));
        $this->assertTrue($this->scopedResolverLocatorHasReference($container, static::EXISTING_RESOLVER_CLASS));
    }

    public function testGivenMissingResolverClassWhenProcessingThenRelationshipIsSkipped(): void
    {
        // Arrange
        $this->writeSchema('foo.resource.yml', [
            'resource' => [
                'name' => 'Foo',
                'shortName' => 'foo',
                'provider' => 'App\\Provider\\FooProvider',
                'includes' => [
                    [
                        'relationshipName' => 'bar',
                        'resolverClass' => static::MISSING_RESOLVER_CLASS,
                    ],
                ],
            ],
        ]);

        $container = $this->buildContainer();

        $pass = new RelationshipConfigurationPass();

        // Act
        $pass->process($container);

        // Assert
        $relationships = $container->getParameter('api_platform.relationships');
        $this->assertArrayNotHasKey('foo.bar', $relationships);
        $this->assertFalse($container->hasDefinition(static::MISSING_RESOLVER_CLASS));
        $this->assertFalse($this->scopedResolverLocatorHasReference($container, static::MISSING_RESOLVER_CLASS));
    }

    public function testGivenMissingResolverClassWhenProcessingThenWarningIsLogged(): void
    {
        // Arrange
        $this->writeSchema('foo.resource.yml', [
            'resource' => [
                'name' => 'Foo',
                'shortName' => 'foo',
                'provider' => 'App\\Provider\\FooProvider',
                'includes' => [
                    [
                        'relationshipName' => 'bar',
                        'resolverClass' => static::MISSING_RESOLVER_CLASS,
                    ],
                ],
            ],
        ]);

        $container = $this->buildContainer();

        $pass = new RelationshipConfigurationPass();

        // Act
        $pass->process($container);

        // Assert
        $log = $container->getCompiler()->getLog();
        $matchingLog = array_filter(
            $log,
            fn (string $entry): bool => str_contains($entry, static::MISSING_RESOLVER_CLASS)
                && str_contains($entry, 'foo.bar'),
        );

        $this->assertNotEmpty($matchingLog, sprintf(
            'Expected a warning mentioning "%s" and "foo.bar". Actual log: %s',
            static::MISSING_RESOLVER_CLASS,
            implode("\n", $log),
        ));
    }

    public function testGivenMixedResolverClassesWhenProcessingThenOnlyExistingAreKept(): void
    {
        // Arrange
        $this->writeSchema('foo.resource.yml', [
            'resource' => [
                'name' => 'Foo',
                'shortName' => 'foo',
                'provider' => 'App\\Provider\\FooProvider',
                'includes' => [
                    [
                        'relationshipName' => 'valid',
                        'resolverClass' => static::EXISTING_RESOLVER_CLASS,
                    ],
                    [
                        'relationshipName' => 'dangling',
                        'resolverClass' => static::MISSING_RESOLVER_CLASS,
                    ],
                ],
            ],
        ]);

        $container = $this->buildContainer();

        $pass = new RelationshipConfigurationPass();

        // Act
        $pass->process($container);

        // Assert
        $relationships = $container->getParameter('api_platform.relationships');
        $this->assertArrayHasKey('foo.valid', $relationships);
        $this->assertArrayNotHasKey('foo.dangling', $relationships);

        $this->assertTrue($this->scopedResolverLocatorHasReference($container, static::EXISTING_RESOLVER_CLASS));
        $this->assertFalse($this->scopedResolverLocatorHasReference($container, static::MISSING_RESOLVER_CLASS));
    }

    protected function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('spryker_api_platform.api_types', [static::API_TYPE]);
        $container->setParameter('spryker_api_platform.source_directories', [$this->tmpDir]);

        $resolverDefinition = new Definition(ApiPlatformRelationshipResolver::class);
        $resolverDefinition->setArgument('$relationships', []);
        $resolverDefinition->setArgument('$providerLocator', new ServiceLocatorArgument([]));
        $resolverDefinition->setArgument('$resolverLocator', new ServiceLocatorArgument([]));
        $container->setDefinition(ApiPlatformRelationshipResolver::class, $resolverDefinition);

        return $container;
    }

    /**
     * @param array<string, mixed> $schema
     */
    protected function writeSchema(string $fileName, array $schema): void
    {
        $schemaDir = sprintf('%s/FakeModule/resources/api/%s', $this->tmpDir, static::API_TYPE);
        file_put_contents(
            sprintf('%s/%s', $schemaDir, $fileName),
            Yaml::dump($schema, 10),
        );
    }

    protected function scopedResolverLocatorHasReference(ContainerBuilder $container, string $serviceId): bool
    {
        $definition = $container->getDefinition(ApiPlatformRelationshipResolver::class);
        $locatorArgument = $definition->getArgument('$resolverLocator');

        if (!$locatorArgument instanceof ServiceLocatorArgument) {
            return false;
        }

        foreach ($locatorArgument->getValues() as $key => $reference) {
            if ($reference instanceof Reference && (string)$reference === $serviceId) {
                return true;
            }

            if ($key === $serviceId) {
                return true;
            }
        }

        return false;
    }

    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($path);
    }
}
