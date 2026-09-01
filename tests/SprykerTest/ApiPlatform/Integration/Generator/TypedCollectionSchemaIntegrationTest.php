<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Generator;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use Codeception\Test\Unit;
use SprykerTest\ApiPlatform\ApiIntegrationTester;
use SprykerTest\ApiPlatform\Fixture\CollectionFixtureResource;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Integration
 * @group Generator
 * @group TypedCollectionSchemaIntegrationTest
 * Add your own group annotations below this line
 */
class TypedCollectionSchemaIntegrationTest extends Unit
{
    protected const string DEFINITIONS_PREFIX = '#/definitions/';

    protected ApiIntegrationTester $tester;

    /**
     * The `@var array<int, \SprykerTest\ApiPlatform\Fixture\CollectionFixtureItem>` docblock on
     * `CollectionFixtureResource::$prices` is the only mechanism left after Task 7A: no attribute,
     * no Spryker-specific resolver. Symfony PropertyInfo parses it inside API Platform's own
     * property-metadata chain, early enough for the container's real `SchemaFactory` to build an
     * `items.$ref` and register the element's own definition.
     */
    public function testGivenTypedCollectionPropertyWhenBuildingSchemaThenItemsCarryRefToRegisteredChildDefinition(): void
    {
        // Arrange
        $schemaFactory = $this->tester->getService(SchemaFactoryInterface::class);
        $this->assertInstanceOf(SchemaFactoryInterface::class, $schemaFactory);

        // Act
        $schema = $schemaFactory->buildSchema(CollectionFixtureResource::class);
        $properties = $this->getRootProperties($schema);

        // Assert — the typed collection property is a JSON Schema array whose items carry a $ref.
        $this->assertSame('array', $properties['prices']['type'] ?? null, 'The "prices" property must be a JSON Schema array.');
        $itemsRef = $properties['prices']['items']['$ref'] ?? null;
        $this->assertIsString($itemsRef, 'The "prices" items must carry a $ref to the element definition.');

        // Assert — the $ref target is present in the schema's definitions, i.e. it is not dangling.
        $childDefinitionKey = $this->stripDefinitionsPrefix($itemsRef);
        $definitions = $schema->getDefinitions();
        $this->assertArrayHasKey(
            $childDefinitionKey,
            $definitions,
            'The definition the "prices" items $ref points at must be registered on the schema.',
        );

        // Assert — the registered child definition describes the element's own fields, so the
        // $ref is not dangling.
        $childProperties = (array)($definitions[$childDefinitionKey]['properties'] ?? []);
        $this->assertArrayHasKey('sku', $childProperties, 'The child definition must describe the element\'s own fields.');
    }

    /**
     * Negative control: `$untypedList` is a bare `array` property with no `@var` docblock. It must
     * not produce a `$ref`, which is what proves the positive assertion above is sensitive to the
     * docblock mechanism rather than passing on something incidental.
     */
    public function testGivenUntypedArrayPropertyWhenBuildingSchemaThenItemsCarryNoRef(): void
    {
        // Arrange
        $schemaFactory = $this->tester->getService(SchemaFactoryInterface::class);
        $this->assertInstanceOf(SchemaFactoryInterface::class, $schemaFactory);

        // Act
        $schema = $schemaFactory->buildSchema(CollectionFixtureResource::class);
        $properties = $this->getRootProperties($schema);

        // Assert — the control is present and array-typed before we assert what it lacks;
        // otherwise a property that silently disappeared from the schema would make the
        // "no $ref" assertion below pass on nothing.
        $this->assertSame('array', $properties['untypedList']['type'] ?? null, 'The "untypedList" property must be a JSON Schema array.');
        $this->assertArrayNotHasKey(
            '$ref',
            (array)($properties['untypedList']['items'] ?? []),
            'A plain array property with no @var docblock must not produce an items.$ref.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getRootProperties(Schema $schema): array
    {
        $rootDefinitionKey = $schema->getRootDefinitionKey();
        $this->assertIsString($rootDefinitionKey, 'buildSchema() must produce a root $ref for the subject class.');

        $definitions = $schema->getDefinitions();
        $this->assertArrayHasKey($rootDefinitionKey, $definitions);

        return (array)($definitions[$rootDefinitionKey]['properties'] ?? []);
    }

    protected function stripDefinitionsPrefix(string $ref): string
    {
        return str_starts_with($ref, static::DEFINITIONS_PREFIX)
            ? substr($ref, strlen(static::DEFINITIONS_PREFIX))
            : $ref;
    }
}
