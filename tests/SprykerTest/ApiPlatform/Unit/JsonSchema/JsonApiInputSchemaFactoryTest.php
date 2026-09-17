<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\JsonSchema;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ArrayObject;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\JsonSchema\JsonApiInputSchemaFactory;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group JsonSchema
 * @group JsonApiInputSchemaFactoryTest
 * Add your own group annotations below this line
 */
class JsonApiInputSchemaFactoryTest extends Unit
{
    protected const string FORMAT_JSON_API = 'jsonapi';

    protected const string RESOURCE_CLASS = 'CustomersBackendResource';

    protected const string RESOURCE_SHORT_NAME = 'customers';

    protected const string FLAT_DEFINITION_KEY = 'customers-customers.write';

    protected ApiUnitTester $tester;

    public function testGivenAPostInputWhenBuildingThenTheBodyIsAJsonApiDocumentWithoutAnIdentifier(): void
    {
        // Act
        $schema = $this->buildSchema(new Post(shortName: static::RESOURCE_SHORT_NAME));

        // Assert
        $data = $this->getDocumentData($schema);

        $this->assertSame(['type', 'attributes'], array_keys($data['properties']));
        $this->assertSame(['type'], $data['required'], 'The server assigns the identifier on create.');
    }

    public function testGivenAPatchInputWhenBuildingThenTheResourceObjectRequiresTypeAndId(): void
    {
        // Act
        $schema = $this->buildSchema(new Patch(shortName: static::RESOURCE_SHORT_NAME));

        // Assert — JSON:API requires both members on the resource object of an update.
        $data = $this->getDocumentData($schema);

        $this->assertSame(['type', 'id', 'attributes'], array_keys($data['properties']));
        $this->assertSame(['type', 'id'], $data['required']);
    }

    public function testGivenAWriteInputWhenBuildingThenTheRequiredListIsCarriedIntoTheAttributes(): void
    {
        // Act
        $schema = $this->buildSchema(new Post(shortName: static::RESOURCE_SHORT_NAME));

        // Assert
        $attributes = $this->getDocumentData($schema)['properties']['attributes'];

        $this->assertSame(['email'], $attributes['required']);
        $this->assertArrayHasKey('email', $attributes['properties']);
    }

    public function testGivenAWriteInputWhenBuildingThenTheDefinitionIsNamedAfterTheOperation(): void
    {
        // Act
        $schema = $this->buildSchema(new Post(shortName: static::RESOURCE_SHORT_NAME));

        // Assert
        $this->assertSame('#/components/schemas/customers.jsonapi-post', $schema['$ref']);
    }

    public function testGivenAReadOnlyPropertyWhenBuildingThenItIsLeftOutOfTheRequestBody(): void
    {
        // Act
        $schema = $this->buildSchema(new Post(shortName: static::RESOURCE_SHORT_NAME));

        // Assert — OpenAPI defines readOnly as response-only, so a request body must not list it.
        $attributes = $this->getDocumentData($schema)['properties']['attributes'];

        $this->assertArrayNotHasKey('uuid', $attributes['properties']);
        $this->assertArrayHasKey('email', $attributes['properties']);
    }

    public function testGivenAReadOnlyPropertyThatIsRequiredWhenBuildingThenItIsAlsoDroppedFromRequired(): void
    {
        // Act
        $schema = $this->buildSchema(new Post(shortName: static::RESOURCE_SHORT_NAME));

        // Assert — a body cannot be required to carry a field it may not carry.
        $this->assertSame(['email'], $this->getDocumentData($schema)['properties']['attributes']['required']);
    }

    public function testGivenNoWritablePropertyWhenBuildingThenTheBodyIsTheBareEnvelope(): void
    {
        // Arrange — an action endpoint addressed by its URI, with every property read-only.
        $factory = new JsonApiInputSchemaFactory($this->createFlatSchemaFactory(readOnlyOnly: true));

        // Act
        $schema = $factory->buildSchema(
            static::RESOURCE_CLASS,
            static::FORMAT_JSON_API,
            Schema::TYPE_INPUT,
            new Post(shortName: static::RESOURCE_SHORT_NAME),
        );

        // Assert
        $this->assertSame(['type'], array_keys($this->getDocumentData($schema)['properties']));
    }

    public function testGivenAReadOperationWhenBuildingThenTheCallIsDelegated(): void
    {
        // Act
        $schema = $this->buildSchema(new Get(shortName: static::RESOURCE_SHORT_NAME), Schema::TYPE_OUTPUT);

        // Assert
        $this->assertSame('#/components/schemas/' . static::FLAT_DEFINITION_KEY, $schema['$ref']);
    }

    public function testGivenAnotherFormatWhenBuildingThenTheCallIsDelegated(): void
    {
        // Act
        $schema = $this->buildSchema(new Post(shortName: static::RESOURCE_SHORT_NAME), Schema::TYPE_INPUT, 'jsonld');

        // Assert
        $this->assertSame('#/components/schemas/' . static::FLAT_DEFINITION_KEY, $schema['$ref']);
    }

    protected function buildSchema(
        Operation $operation,
        string $type = Schema::TYPE_INPUT,
        string $format = self::FORMAT_JSON_API
    ): Schema {
        $factory = new JsonApiInputSchemaFactory($this->createFlatSchemaFactory());

        return $factory->buildSchema(static::RESOURCE_CLASS, $format, $type, $operation, null, null, false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDocumentData(Schema $schema): array
    {
        $definitionName = substr((string)$schema['$ref'], strlen('#/components/schemas/'));

        return $schema->getDefinitions()[$definitionName]['properties']['data'];
    }

    protected function createFlatSchemaFactory(bool $readOnlyOnly = false): SchemaFactoryInterface
    {
        $flatSchemaFactoryMock = $this->createMock(SchemaFactoryInterface::class);
        $flatSchemaFactoryMock->method('buildSchema')->willReturnCallback(
            function () use ($readOnlyOnly): Schema {
                $schema = new Schema(Schema::VERSION_OPENAPI);
                $schema->setDefinitions(new ArrayObject([
                    static::FLAT_DEFINITION_KEY => [
                        'type' => 'object',
                        'properties' => $readOnlyOnly
                            ? ['status' => ['type' => 'string', 'readOnly' => true]]
                            : [
                                'email' => ['type' => 'string'],
                                'company' => ['type' => 'string'],
                                'uuid' => ['type' => 'string', 'readOnly' => true],
                            ],
                        'required' => ['email', 'uuid'],
                    ],
                ]));
                $schema['$ref'] = '#/components/schemas/' . static::FLAT_DEFINITION_KEY;

                return $schema;
            },
        );

        return $flatSchemaFactoryMock;
    }
}
