<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\OpenApi\Model\MediaType;
use ArrayObject;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\SchemaReferenceResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group ErrorResponse
 * @group SchemaReferenceResolverTest
 * Add your own group annotations below this line
 */
class SchemaReferenceResolverTest extends Unit
{
    protected const string SCHEMA_NAME_RESOURCE = 'categories.jsonapi';

    protected const string SCHEMA_NAME_API_PLATFORM_ERROR = 'Error.jsonapi';

    protected const string REFERENCE_RESOURCE = '#/components/schemas/categories.jsonapi';

    protected const string REFERENCE_FOREIGN = '#/components/responses/NotFound';

    protected const string REFERENCE_KEY = '$ref';

    protected const string DESCRIPTION_INVALID_UTF8 = "Legacy description \xB1\x31";

    protected ApiUnitTester $tester;

    public function testGivenMediaTypeReferencingAComponentSchemaWhenResolvingThenTheSchemaNameIsReturned(): void
    {
        // Act
        $schemaName = (new SchemaReferenceResolver())->resolveSchemaName(new MediaType(schema: new ArrayObject([static::REFERENCE_KEY => static::REFERENCE_RESOURCE])));

        // Assert
        $this->assertSame(static::SCHEMA_NAME_RESOURCE, $schemaName);
    }

    public function testGivenNoSchemaOrAForeignReferenceWhenResolvingThenNullIsReturned(): void
    {
        // Arrange
        $resolver = new SchemaReferenceResolver();

        // Act & Assert
        $this->assertNull($resolver->resolveSchemaName(new MediaType()));
        $this->assertNull($resolver->resolveSchemaName(new MediaType(schema: new ArrayObject([static::REFERENCE_KEY => static::REFERENCE_FOREIGN]))));
        $this->assertNull($resolver->resolveSchemaName(static::REFERENCE_RESOURCE));
    }

    public function testGivenApiPlatformErrorSchemaReferenceWhenCheckingThenItIsRecognised(): void
    {
        // Arrange
        $resolver = new SchemaReferenceResolver();

        // Act & Assert
        $this->assertTrue($resolver->isApiPlatformErrorSchema(new MediaType(schema: $resolver->createSchemaReference('#/components/schemas/' . static::SCHEMA_NAME_API_PLATFORM_ERROR))));
        $this->assertFalse($resolver->isApiPlatformErrorSchema(new MediaType(schema: new ArrayObject([static::REFERENCE_KEY => static::REFERENCE_RESOURCE]))));
        $this->assertFalse($resolver->isApiPlatformErrorSchemaName(null));
    }

    public function testGivenNestedSchemaWhenCollectingReferencesThenEveryReferencedNameIsFound(): void
    {
        // Arrange
        $schema = ['properties' => ['data' => ['items' => ['allOf' => [[static::REFERENCE_KEY => static::REFERENCE_RESOURCE]]]], 'error' => [static::REFERENCE_KEY => '#/components/schemas/Error']]];

        // Act
        $schemaNames = (new SchemaReferenceResolver())->collectNestedSchemaNames($schema);

        // Assert
        $this->assertSame([static::SCHEMA_NAME_RESOURCE, 'Error'], $schemaNames);
    }

    public function testGivenSchemaWithInvalidUtf8WhenCollectingReferencesThenEveryReferencedNameIsStillFound(): void
    {
        // Arrange
        $schema = new ArrayObject([
            'description' => static::DESCRIPTION_INVALID_UTF8,
            'properties' => new ArrayObject(['errors' => ['items' => [static::REFERENCE_KEY => '#/components/schemas/' . static::SCHEMA_NAME_API_PLATFORM_ERROR]]]),
        ]);

        // Act
        $schemaNames = (new SchemaReferenceResolver())->collectNestedSchemaNames($schema);

        // Assert
        $this->assertSame([static::SCHEMA_NAME_API_PLATFORM_ERROR], $schemaNames);
    }

    public function testGivenNoReferenceOrANonSchemaValueWhenCollectingReferencesThenNothingIsFound(): void
    {
        // Arrange
        $resolver = new SchemaReferenceResolver();

        // Act & Assert
        $this->assertSame([], $resolver->collectNestedSchemaNames(['type' => 'object', 'properties' => ['code' => ['type' => 'string']]]));
        $this->assertSame([], $resolver->collectNestedSchemaNames([static::REFERENCE_KEY => static::REFERENCE_FOREIGN]));
        $this->assertSame([], $resolver->collectNestedSchemaNames(static::REFERENCE_RESOURCE));
    }
}
