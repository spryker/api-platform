<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ArrayObject;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseBuilder;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseDocumenter;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\GlueApiErrorSchema;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ProviderNotFoundErrorResolver;
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
 * @group ErrorResponseDocumenterTest
 * Add your own group annotations below this line
 */
class ErrorResponseDocumenterTest extends Unit
{
    protected const string MIME_JSON_API = 'application/vnd.api+json';

    protected const string MIME_JSON = 'application/json';

    protected const string SCHEMA_RESOURCE = 'categories.jsonapi';

    protected const string SCHEMA_CUSTOM_ERROR = 'custom-error.jsonapi';

    protected const string DESCRIPTION_SUCCESS = 'Category returned.';

    protected const string DESCRIPTION_API_PLATFORM_NOT_FOUND = 'Not found';

    protected const string DESCRIPTION_DECLARED_CONFLICT = 'Category key already taken.';

    protected const int STATUS_OK = 200;

    protected const int STATUS_NOT_FOUND = 404;

    protected const int STATUS_CONFLICT = 409;

    /**
     * @var array<string, array<string>>
     */
    protected const array ERROR_FORMATS = ['jsonapi' => [self::MIME_JSON_API, self::MIME_JSON]];

    protected ApiUnitTester $tester;

    public function testGivenErrorResponseWithoutContentWhenDocumentingThenTheGlueSchemaAndExamplesAreAttachedUnderEveryErrorFormat(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_OK => new Response(static::DESCRIPTION_SUCCESS, $this->createContent(static::SCHEMA_RESOURCE)),
            static::STATUS_NOT_FOUND => new Response(static::DESCRIPTION_API_PLATFORM_NOT_FOUND),
        ]);

        // Act
        $operation = $this->createDocumenter()->document($operation, new Get(), []);

        // Assert
        $responses = $operation->getResponses() ?? [];
        $notFound = $responses[static::STATUS_NOT_FOUND];
        $this->assertSame([static::MIME_JSON_API, static::MIME_JSON], array_keys(($notFound->getContent() ?? new ArrayObject())->getArrayCopy()));
        $this->assertSame(GlueApiErrorSchema::REFERENCE, $notFound->getContent()[static::MIME_JSON]->getSchema()['$ref']);
        $this->assertArrayHasKey(ErrorResponseBuilder::EXAMPLE_NOT_FOUND, $notFound->getContent()[static::MIME_JSON_API]->getExamples()?->getArrayCopy() ?? []);
        $this->assertNotSame(static::DESCRIPTION_API_PLATFORM_NOT_FOUND, $notFound->getDescription());
        $this->assertSame(static::DESCRIPTION_SUCCESS, $responses[static::STATUS_OK]->getDescription());
        $this->assertSame([static::MIME_JSON_API], array_keys(($responses[static::STATUS_OK]->getContent() ?? new ArrayObject())->getArrayCopy()));
    }

    public function testGivenHandWrittenErrorContentWhenDocumentingThenItIsKept(): void
    {
        // Arrange
        $customContent = $this->createContent(static::SCHEMA_CUSTOM_ERROR);
        $operation = new Operation(responses: [static::STATUS_CONFLICT => new Response(static::DESCRIPTION_DECLARED_CONFLICT, $customContent)]);

        // Act
        $operation = $this->createDocumenter()->document($operation, new Get(), []);

        // Assert
        $response = ($operation->getResponses() ?? [])[static::STATUS_CONFLICT];
        $this->assertSame($customContent, $response->getContent());
        $this->assertSame(static::DESCRIPTION_DECLARED_CONFLICT, $response->getDescription());
    }

    protected function createDocumenter(): ErrorResponseDocumenter
    {
        $errorResponseBuilder = new ErrorResponseBuilder(
            new ApiPlatformConfig([], '', '', [ApiPlatformConfig::API_TYPE_STOREFRONT], false),
            new ProviderNotFoundErrorResolver(),
            static::ERROR_FORMATS,
        );

        return new ErrorResponseDocumenter($errorResponseBuilder, new SchemaReferenceResolver(), static::ERROR_FORMATS);
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
