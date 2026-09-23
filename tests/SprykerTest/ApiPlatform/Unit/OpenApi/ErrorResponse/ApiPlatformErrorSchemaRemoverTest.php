<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ApiPlatformErrorSchemaRemover;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\PathItemOperationAccessor;
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
 * @group ApiPlatformErrorSchemaRemoverTest
 * Add your own group annotations below this line
 */
class ApiPlatformErrorSchemaRemoverTest extends Unit
{
    protected const string PATH = '/categories';

    protected const string MIME_JSON_API = 'application/vnd.api+json';

    protected const string SCHEMA_RESOURCE = 'categories.jsonapi';

    protected const string SCHEMA_ERROR = 'Error';

    protected const string SCHEMA_ERROR_JSON_API = 'Error.jsonapi';

    protected const string SCHEMA_VIOLATION = 'ConstraintViolation';

    protected const int STATUS_BAD_REQUEST = 400;

    protected const string DESCRIPTION_INVALID_UTF8 = "Legacy description \xB1\x31";

    protected ApiUnitTester $tester;

    public function testGivenApiPlatformErrorSchemasWhenRemovingThenTheOneAResponseReferencesStaysWithWhatItReferences(): void
    {
        // Arrange
        $schemas = new ArrayObject([
            static::SCHEMA_RESOURCE => ['type' => 'object'],
            static::SCHEMA_ERROR => ['type' => 'object'],
            static::SCHEMA_ERROR_JSON_API => ['properties' => ['errors' => ['items' => ['$ref' => '#/components/schemas/' . static::SCHEMA_ERROR]]]],
            static::SCHEMA_VIOLATION => ['type' => 'object'],
        ]);
        $openApi = $this->createOpenApi(new Operation(responses: [static::STATUS_BAD_REQUEST => new Response('Bad request', $this->createContent(static::SCHEMA_ERROR_JSON_API))]), $schemas);

        // Act
        $this->createRemover()->remove($openApi, $schemas);

        // Assert
        $this->assertSame([static::SCHEMA_ERROR, static::SCHEMA_ERROR_JSON_API, static::SCHEMA_RESOURCE], array_keys($schemas->getArrayCopy()));
    }

    public function testGivenApiPlatformErrorSchemasReferencedOnlyByEachOtherWhenRemovingThenAllDisappear(): void
    {
        // Arrange
        $schemas = new ArrayObject([
            static::SCHEMA_RESOURCE => ['type' => 'object'],
            static::SCHEMA_ERROR => ['type' => 'object'],
            static::SCHEMA_ERROR_JSON_API => ['properties' => ['errors' => ['items' => ['$ref' => '#/components/schemas/' . static::SCHEMA_ERROR]]]],
        ]);
        $openApi = $this->createOpenApi(new Operation(responses: [static::STATUS_BAD_REQUEST => new Response('Bad request', $this->createContent(static::SCHEMA_RESOURCE))]), $schemas);

        // Act
        $this->createRemover()->remove($openApi, $schemas);

        // Assert
        $this->assertSame([static::SCHEMA_RESOURCE], array_keys($schemas->getArrayCopy()));
    }

    public function testGivenSchemaThatCannotBeJsonEncodedWhenRemovingThenTheErrorSchemaItReferencesStays(): void
    {
        // Arrange
        $schemas = new ArrayObject([
            static::SCHEMA_RESOURCE => [
                'description' => static::DESCRIPTION_INVALID_UTF8,
                'properties' => ['error' => ['$ref' => '#/components/schemas/' . static::SCHEMA_ERROR_JSON_API]],
            ],
            static::SCHEMA_ERROR => ['type' => 'object'],
            static::SCHEMA_ERROR_JSON_API => ['properties' => ['errors' => ['items' => ['$ref' => '#/components/schemas/' . static::SCHEMA_ERROR]]]],
            static::SCHEMA_VIOLATION => ['type' => 'object'],
        ]);
        $openApi = $this->createOpenApi(new Operation(responses: [static::STATUS_BAD_REQUEST => new Response('Bad request', $this->createContent(static::SCHEMA_RESOURCE))]), $schemas);

        // Act
        $this->createRemover()->remove($openApi, $schemas);

        // Assert
        $this->assertSame([static::SCHEMA_ERROR, static::SCHEMA_ERROR_JSON_API, static::SCHEMA_RESOURCE], array_keys($schemas->getArrayCopy()));
    }

    protected function createRemover(): ApiPlatformErrorSchemaRemover
    {
        return new ApiPlatformErrorSchemaRemover(new PathItemOperationAccessor(), new SchemaReferenceResolver());
    }

    /**
     * @param \ArrayObject<string, mixed> $schemas
     */
    protected function createOpenApi(Operation $operation, ArrayObject $schemas): OpenApi
    {
        $paths = new Paths();
        $paths->addPath(static::PATH, new PathItem(post: $operation));

        return new OpenApi(new Info('Spryker Glue API', '0.0.0'), [], $paths, new Components(schemas: $schemas));
    }

    /**
     * @return \ArrayObject<string, \ApiPlatform\OpenApi\Model\MediaType>
     */
    protected function createContent(string $schemaName): ArrayObject
    {
        return new ArrayObject([
            static::MIME_JSON_API => new MediaType(schema: (new SchemaReferenceResolver())->createSchemaReference('#/components/schemas/' . $schemaName)),
        ]);
    }
}
