<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Parser;

use Codeception\Test\Unit;
use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Schema\Parser\SchemaParser;
use Spryker\ApiPlatform\Schema\Validation\Finder\ValidationSchemaFinderInterface;
use Spryker\ApiPlatform\Schema\Validation\Loader\ValidationSchemaLoaderInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Parser
 * @group SchemaParserTest
 * Add your own group annotations below this line
 */
class SchemaParserTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenValidSchemaWhenParsingThenReturnsNormalizedData(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('Customer', $result['name']);
    }

    public function testGivenSchemaWithoutResourceKeyWhenParsingThenThrowsException(): void
    {
        // Arrange
        $parser = $this->createSchemaParser();

        // Expect
        $this->expectException(ApiSchemaValidationException::class);
        $this->expectExceptionMessage('Schema must have a "resource" key');

        // Act
        $parser->parse([], new SplFileInfo(__FILE__));
    }

    public function testGivenSchemaWithOperationsWhenParsingThenNormalizesOperations(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['operations' => [['type' => 'Get'], ['type' => 'Post']]]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('Get', $result['operations']);
    }

    public function testGivenSchemaWithPropertiesWhenParsingThenNormalizesProperties(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['properties' => ['id' => ['type' => 'int']]]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('integer', $result['properties']['id']['type']);
    }

    public function testGivenPropertyTypeIntWhenParsingThenConvertsToInteger(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['properties' => ['count' => ['type' => 'int']]]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('integer', $result['properties']['count']['type']);
    }

    public function testGivenPyzPathWhenParsingThenDetectsProjectLayer(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $file = new SplFileInfo('/path/to/Pyz/Module/file.yaml');
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, $file);

        // Assert
        $this->assertEquals('project', $result['sourceLayer']);
    }

    public function testGivenSprykerFeaturePathWhenParsingThenDetectsFeatureLayer(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $file = new SplFileInfo('/path/to/SprykerFeature/Module/file.yaml');
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, $file);

        // Assert
        $this->assertEquals('feature', $result['sourceLayer']);
    }

    public function testGivenValidationSchemasWhenParsingThenAddsValidationSourceFilesAsArray(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $file = new SplFileInfo('/path/to/resources/api/backend/customers.resource.yaml');
        $validationSchemas = [
            'backend_customers' => [
                [
                    'schema' => ['post' => ['name' => ['NotBlank']]],
                    'sourceFile' => '/path/to/validation/customers.validation.yaml',
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, $file, $validationSchemas);

        // Assert
        $this->assertArrayHasKey('validationSourceFiles', $result);
        $this->assertIsArray($result['validationSourceFiles']);
        $this->assertCount(1, $result['validationSourceFiles']);
        $this->assertEquals('/path/to/validation/customers.validation.yaml', $result['validationSourceFiles'][0]);
    }

    protected function createSchemaParser(): SchemaParser
    {
        $validationSchemaFinder = $this->makeEmpty(ValidationSchemaFinderInterface::class);
        $validationSchemaLoader = $this->makeEmpty(ValidationSchemaLoaderInterface::class);

        return new SchemaParser($validationSchemaFinder, $validationSchemaLoader);
    }
}
