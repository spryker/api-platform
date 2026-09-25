<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Envelope;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\SchemaSourceResolver;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use Symfony\Component\Yaml\Yaml;

/**
 * Conserves the resource short name across the two passes the envelope dimension depends on: the
 * `shortName` each `.resource.yml` declares, parsed here, against the one its generated class
 * reflects. The parse shares no code with the reflection, on purpose.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Envelope
 * @group ResourceShortNameConservationTest
 * Add your own group annotations below this line
 */
class ResourceShortNameConservationTest extends Unit
{
    protected const string GENERATED_RESOURCE_DIRECTORY = '/src/Generated/Api/Storefront';

    protected const string RESOURCE_NAMESPACE_PREFIX = 'Generated\\Api\\Storefront\\';

    protected const string SCHEMA_ROOT_KEY = 'resource';

    protected const string SCHEMA_SHORT_NAME_KEY = 'shortName';

    public function testGivenEveryGeneratedResourceWhenReflectingItsShortNameThenOneIsAlwaysFound(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();

        // Act
        $withoutShortName = [];
        foreach ($this->generatedResourceClasses() as $resourceClass) {
            if ($loader->shortName($resourceClass) === null) {
                $withoutShortName[] = $resourceClass;
            }
        }

        // Assert
        sort($withoutShortName);
        $this->assertSame([], $withoutShortName, 'These generated resources carry no shortName, so no response of theirs can be judged.');
    }

    public function testGivenEveryGeneratedResourceWhenParsingItsSourceSchemasThenTheyNameTheSameResource(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();
        $sourceResolver = new SchemaSourceResolver();

        // Act
        $disagreements = [];
        foreach ($this->generatedResourceClasses() as $resourceClass) {
            $reflectedShortName = $loader->shortName($resourceClass);
            if ($reflectedShortName === null) {
                continue;
            }

            foreach ($this->declaredShortNamesIn($sourceResolver->schemaFilesFor($resourceClass)) as $file => $declaredShortName) {
                if ($declaredShortName !== $reflectedShortName) {
                    $disagreements[] = sprintf('%s declares "%s" but %s reflects "%s"', $file, $declaredShortName, $resourceClass, $reflectedShortName);
                }
            }
        }

        // Assert
        sort($disagreements);
        $this->assertSame([], $disagreements, 'A schema and its generated class disagree on the resource short name.');
    }

    public function testGivenEveryGeneratedResourceWhenResolvingItsSourceSchemasThenAtLeastOneDeclaresTheShortName(): void
    {
        // Arrange
        $loader = ContractCoverageFactory::createSchemaTruthLoader();
        $sourceResolver = new SchemaSourceResolver();

        // Act
        $withoutDeclaringSource = [];
        foreach ($this->generatedResourceClasses() as $resourceClass) {
            if ($loader->shortName($resourceClass) === null) {
                continue;
            }

            if ($this->declaredShortNamesIn($sourceResolver->schemaFilesFor($resourceClass)) === []) {
                $withoutDeclaringSource[] = $resourceClass;
            }
        }

        // Assert — a class whose sources declare nothing is a name only the generator knows.
        sort($withoutDeclaringSource);
        $this->assertSame([], $withoutDeclaringSource, 'No source schema of these resources declares the short name their class carries.');
    }

    /**
     * The `shortName` each of a resource's source schemas declares, keyed by file. A schema that
     * declares none contributes operations to a resource another schema named, so it is absent from
     * the result rather than counted as a disagreement.
     *
     * @param array<string> $schemaFiles
     *
     * @return array<string, string>
     */
    protected function declaredShortNamesIn(array $schemaFiles): array
    {
        $shortNames = [];

        foreach ($schemaFiles as $file) {
            $this->assertFileExists($file, 'The generated header names a schema file that is not on disk.');

            $resource = (Yaml::parseFile($file) ?: [])[static::SCHEMA_ROOT_KEY] ?? null;
            if (!is_array($resource)) {
                continue;
            }

            $shortName = $resource[static::SCHEMA_SHORT_NAME_KEY] ?? null;
            if (is_string($shortName) && $shortName !== '') {
                $shortNames[$file] = $shortName;
            }
        }

        return $shortNames;
    }

    /**
     * Asserts the tree is populated: every conservation check below ends in a comparison over this
     * list, which an empty tree would turn into a pass over nothing.
     *
     * @return array<class-string>
     */
    protected function generatedResourceClasses(): array
    {
        $classes = [];

        foreach (glob($this->projectRoot() . static::GENERATED_RESOURCE_DIRECTORY . '/*.php') ?: [] as $file) {
            /** @var class-string $className */
            $className = static::RESOURCE_NAMESPACE_PREFIX . basename($file, '.php');
            if (class_exists($className)) {
                $classes[] = $className;
            }
        }

        $this->assertNotEmpty($classes, 'No generated resource was found — run vendor/bin/glue api:generate first.');

        return $classes;
    }

    protected function projectRoot(): string
    {
        return defined('APPLICATION_ROOT_DIR') ? APPLICATION_ROOT_DIR : dirname(codecept_data_dir(), 3);
    }
}
