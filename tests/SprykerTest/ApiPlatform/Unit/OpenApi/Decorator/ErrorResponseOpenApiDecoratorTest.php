<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\Decorator;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\Schema;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\OpenApi\Decorator\ErrorResponseOpenApiDecorator;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ApiPlatformErrorSchemaRemover;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\DefaultErrorResponseAdder;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseBuilder;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseDocumenter;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\GlueApiErrorSchema;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\OperationMetadataResolver;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\PathItemOperationAccessor;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ProviderNotFoundErrorResolver;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\RequestAttributesResolver;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\SchemaReferenceResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group Decorator
 * @group ErrorResponseOpenApiDecoratorTest
 * Add your own group annotations below this line
 */
class ErrorResponseOpenApiDecoratorTest extends Unit
{
    protected const string PATH_ITEM = '/categories/{categoryKey}';

    protected const string PATH_COLLECTION = '/categories';

    protected const string PATH_TOKENS = '/access-tokens';

    protected const string METHOD_GET = 'get';

    protected const string METHOD_POST = 'post';

    protected const string METHOD_PATCH = 'patch';

    protected const string METHOD_DELETE = 'delete';

    protected const string MIME_JSON_API = 'application/vnd.api+json';

    protected const string MIME_JSON = 'application/json';

    protected const string MIME_PROBLEM_JSON = 'application/problem+json';

    protected const string SECURITY_BACK_OFFICE_USER = "is_granted('ROLE_BACK_OFFICE_USER')";

    protected const string SCHEMA_RESOURCE = 'categories.jsonapi';

    protected const string SCHEMA_REQUEST = 'categories.jsonapi-post';

    protected const string SCHEMA_CUSTOM_ERROR = 'custom-error.jsonapi';

    protected const string SCHEMA_API_PLATFORM_ERROR = 'Error';

    protected const string SCHEMA_API_PLATFORM_ERROR_JSON_API = 'Error.jsonapi';

    protected const string SCHEMA_API_PLATFORM_VIOLATION = 'ConstraintViolation';

    protected const string SCHEMA_API_PLATFORM_VIOLATION_JSON_API = 'ConstraintViolation.jsonapi';

    protected const string REFERENCE_PREFIX = '#/components/schemas/';

    protected const string DESCRIPTION_CATEGORY_NOT_FOUND = 'Category not found.';

    protected const string DESCRIPTION_CONFLICT = 'Category key already taken.';

    protected const string DESCRIPTION_BAD_CREDENTIALS = 'The credentials could not be authenticated.';

    protected const string DESCRIPTION_API_PLATFORM_INVALID_INPUT = 'Invalid input';

    protected const string DESCRIPTION_API_PLATFORM_FORBIDDEN = 'Forbidden';

    protected const string DESCRIPTION_API_PLATFORM_NOT_FOUND = 'Not found';

    protected const string DESCRIPTION_API_PLATFORM_ERROR_OCCURRED = 'An error occurred';

    protected const string DESCRIPTION_GLUE_NOT_FOUND = 'Resource not found.';

    protected const string ATTRIBUTE_CATEGORY_KEY = 'categoryKey';

    protected const int STATUS_OK = 200;

    protected const int STATUS_CREATED = 201;

    protected const int STATUS_NO_CONTENT = 204;

    protected const int STATUS_BAD_REQUEST = 400;

    protected const int STATUS_UNAUTHORIZED = 401;

    protected const int STATUS_FORBIDDEN = 403;

    protected const int STATUS_NOT_FOUND = 404;

    protected const int STATUS_CONFLICT = 409;

    protected const int STATUS_UNPROCESSABLE = 422;

    protected const int STATUS_METHOD_NOT_ALLOWED = 405;

    protected const string RESPONSE_KEY_DEFAULT = 'default';

    protected const string ERROR_CODE_UNSUPPORTED_FILTER_FORMAT = '011';

    protected const string DESCRIPTION_ANY_ERROR = 'Anything else this endpoint answers.';

    protected const string DETAIL_NOT_ACCEPTABLE = 'Requested format "text/plain" is not supported. Supported MIME types are "application/vnd.api+json", "application/json".';

    protected const string DETAIL_UNSUPPORTED_MEDIA_TYPE = 'The content-type "text/plain" is not supported. Supported MIME types are "application/vnd.api+json", "application/json".';

    protected const string STATUS_UNSUPPORTED_MEDIA_TYPE_TEXT = '415';

    /**
     * @var array<string, array<string>>
     */
    protected const array ERROR_FORMATS = ['jsonapi' => [self::MIME_JSON_API, self::MIME_JSON]];

    protected ApiUnitTester $tester;

    public function testGivenDeclaredErrorResponseWithoutContentWhenDecoratingThenItGetsTheGlueErrorSchemaAndAnExample(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_OK => new Response('Category returned.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
            static::STATUS_NOT_FOUND => new Response(static::DESCRIPTION_CATEGORY_NOT_FOUND),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_ITEM => new PathItem(get: $operation)]),
            [$this->createKey(static::METHOD_GET, static::PATH_ITEM) => new Get(security: static::SECURITY_BACK_OFFICE_USER)],
        );

        // Act
        $response = $this->getResponse($decorator(), static::PATH_ITEM, static::METHOD_GET, static::STATUS_NOT_FOUND);

        // Assert
        $this->assertSame(static::DESCRIPTION_CATEGORY_NOT_FOUND, $response->getDescription());
        $this->assertSame(GlueApiErrorSchema::REFERENCE, $this->getSchemaReference($response, static::MIME_JSON_API));
        $this->assertSame(GlueApiErrorSchema::REFERENCE, $this->getSchemaReference($response, static::MIME_JSON));
        $this->assertArrayHasKey(ErrorResponseBuilder::EXAMPLE_NOT_FOUND, $this->getExamples($response, static::MIME_JSON_API));
    }

    public function testGivenProtectedItemOperationWhenDecoratingThenTheMissingAuthAndNotFoundResponsesAreAdded(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_OK => new Response('Category returned.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_ITEM => new PathItem(get: $operation)]),
            [$this->createKey(static::METHOD_GET, static::PATH_ITEM) => new Get(security: static::SECURITY_BACK_OFFICE_USER)],
        );

        // Act
        $decoratedOperation = $this->getOperation($decorator(), static::PATH_ITEM, static::METHOD_GET);

        // Assert
        $this->assertSame(
            [static::STATUS_OK, static::STATUS_BAD_REQUEST, static::STATUS_UNAUTHORIZED, static::STATUS_FORBIDDEN, static::STATUS_NOT_FOUND, static::RESPONSE_KEY_DEFAULT],
            array_keys($this->getResponses($decoratedOperation)),
        );
        $this->assertNotEmpty($this->getResponses($decoratedOperation)[static::STATUS_UNAUTHORIZED]->getDescription());
        $this->assertSame(
            GlueApiErrorSchema::REFERENCE,
            $this->getSchemaReference($this->getResponses($decoratedOperation)[static::STATUS_FORBIDDEN], static::MIME_JSON_API),
        );
        $this->assertNull($decoratedOperation->getSecurity());
    }

    public function testGivenPublicItemOperationWhenDecoratingThenNoAuthResponsesAreAddedAndTheSecurityRequirementIsCleared(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_OK => new Response('Category returned.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_ITEM => new PathItem(get: $operation)]),
            [$this->createKey(static::METHOD_GET, static::PATH_ITEM) => new Get()],
        );

        // Act
        $decoratedOperation = $this->getOperation($decorator(), static::PATH_ITEM, static::METHOD_GET);

        // Assert
        $this->assertSame(
            [static::STATUS_OK, static::STATUS_BAD_REQUEST, static::STATUS_NOT_FOUND, static::RESPONSE_KEY_DEFAULT],
            array_keys($this->getResponses($decoratedOperation)),
        );
        $this->assertSame([], $decoratedOperation->getSecurity());
    }

    public function testGivenPublicOperationDeclaringUnauthorizedWhenDecoratingThenTheResponseKeepsTheSchemaWithoutExamples(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_CREATED => new Response('Access token issued.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
            static::STATUS_UNAUTHORIZED => new Response(static::DESCRIPTION_BAD_CREDENTIALS),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_TOKENS => new PathItem(post: $operation)]),
            [$this->createKey(static::METHOD_POST, static::PATH_TOKENS) => new Post()],
        );

        // Act
        $response = $this->getResponse($decorator(), static::PATH_TOKENS, static::METHOD_POST, static::STATUS_UNAUTHORIZED);

        // Assert
        $this->assertSame(static::DESCRIPTION_BAD_CREDENTIALS, $response->getDescription());
        $this->assertSame(GlueApiErrorSchema::REFERENCE, $this->getSchemaReference($response, static::MIME_JSON_API));
        $this->assertSame([], $this->getExamples($response, static::MIME_JSON_API));
    }

    public function testGivenProtectedCollectionOperationWhenDecoratingThenNoNotFoundResponseIsAdded(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_OK => new Response('Categories returned.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_COLLECTION => new PathItem(get: $operation)]),
            [$this->createKey(static::METHOD_GET, static::PATH_COLLECTION) => new GetCollection(security: static::SECURITY_BACK_OFFICE_USER)],
        );

        // Act
        $decoratedOperation = $this->getOperation($decorator(), static::PATH_COLLECTION, static::METHOD_GET);

        // Assert
        $this->assertSame(
            [static::STATUS_OK, static::STATUS_BAD_REQUEST, static::STATUS_UNAUTHORIZED, static::STATUS_FORBIDDEN, static::RESPONSE_KEY_DEFAULT],
            array_keys($this->getResponses($decoratedOperation)),
        );
    }

    public function testGivenReadOperationWhenDecoratingThenTheAddedBadRequestShowsTheFilterFormatRejection(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_OK => new Response('Category returned.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_ITEM => new PathItem(get: $operation)]),
            [$this->createKey(static::METHOD_GET, static::PATH_ITEM) => new Get()],
        );

        // Act
        $response = $this->getResponse($decorator(), static::PATH_ITEM, static::METHOD_GET, static::STATUS_BAD_REQUEST);

        // Assert
        $this->assertStringContainsString('filter[resource.property]', (string)$response->getDescription());
        $example = $this->getExamples($response, static::MIME_JSON_API)[ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_FILTER_FORMAT];
        $this->assertSame(static::ERROR_CODE_UNSUPPORTED_FILTER_FORMAT, $example->getValue()['errors'][0]['code']);
    }

    public function testGivenOperationWithMetadataWhenDecoratingThenADefaultResponseDocumentsTheMethodAndFormatRejections(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_CREATED => new Response('Category created.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_COLLECTION => new PathItem(post: $operation)]),
            [$this->createKey(static::METHOD_POST, static::PATH_COLLECTION) => new Post()],
        );

        // Act
        $decoratedOperation = $this->getOperation($decorator(), static::PATH_COLLECTION, static::METHOD_POST);

        // Assert
        $this->assertSame(
            [static::STATUS_CREATED, static::STATUS_BAD_REQUEST, static::RESPONSE_KEY_DEFAULT],
            array_keys($this->getResponses($decoratedOperation)),
        );
        $default = $this->getResponses($decoratedOperation)[static::RESPONSE_KEY_DEFAULT];
        $this->assertSame(GlueApiErrorSchema::REFERENCE, $this->getSchemaReference($default, static::MIME_JSON_API));
        $examples = $this->getExamples($default, static::MIME_JSON_API);
        $this->assertSame(
            [ErrorResponseBuilder::EXAMPLE_METHOD_NOT_ALLOWED, ErrorResponseBuilder::EXAMPLE_NOT_ACCEPTABLE],
            array_keys($examples),
        );
        $this->assertSame(static::STATUS_METHOD_NOT_ALLOWED, $examples[ErrorResponseBuilder::EXAMPLE_METHOD_NOT_ALLOWED]->getValue()['errors'][0]['status']);
        $this->assertSame(static::DETAIL_NOT_ACCEPTABLE, $examples[ErrorResponseBuilder::EXAMPLE_NOT_ACCEPTABLE]->getValue()['errors'][0]['detail']);
    }

    public function testGivenBodyOperationWithInputFormatsWhenDecoratingThenTheDefaultResponseAlsoDocumentsTheContentTypeRejection(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_CREATED => new Response('Category created.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_COLLECTION => new PathItem(post: $operation)]),
            [$this->createKey(static::METHOD_POST, static::PATH_COLLECTION) => new Post(inputFormats: static::ERROR_FORMATS)],
        );

        // Act
        $default = $this->getResponses($this->getOperation($decorator(), static::PATH_COLLECTION, static::METHOD_POST))[static::RESPONSE_KEY_DEFAULT];

        // Assert
        $this->assertStringContainsString(static::STATUS_UNSUPPORTED_MEDIA_TYPE_TEXT, (string)$default->getDescription());
        $example = $this->getExamples($default, static::MIME_JSON_API)[ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_MEDIA_TYPE];
        $this->assertSame(static::DETAIL_UNSUPPORTED_MEDIA_TYPE, $example->getValue()['errors'][0]['detail']);
    }

    public function testGivenProtectedDeleteOperationWhenDecoratingThenTheBadRequestIsAddedWithTheFilterRejectionOnly(): void
    {
        // Arrange
        $operation = new Operation(responses: [static::STATUS_NO_CONTENT => new Response('Category deleted.')]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_ITEM => new PathItem(delete: $operation)]),
            [$this->createKey(static::METHOD_DELETE, static::PATH_ITEM) => new Delete(security: static::SECURITY_BACK_OFFICE_USER)],
        );

        // Act
        $decoratedOperation = $this->getOperation($decorator(), static::PATH_ITEM, static::METHOD_DELETE);

        // Assert
        $this->assertSame(
            [static::STATUS_NO_CONTENT, static::STATUS_BAD_REQUEST, static::STATUS_UNAUTHORIZED, static::STATUS_FORBIDDEN, static::STATUS_NOT_FOUND, static::RESPONSE_KEY_DEFAULT],
            array_keys($this->getResponses($decoratedOperation)),
        );
        $this->assertSame(
            [ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_FILTER_FORMAT],
            array_keys($this->getExamples($this->getResponses($decoratedOperation)[static::STATUS_BAD_REQUEST], static::MIME_JSON_API)),
        );
    }

    public function testGivenApiPlatformDescriptionsWhenDecoratingThenTheyAreReplacedWhileDeclaredOnesAreKept(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_OK => new Response('Category updated.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
            static::STATUS_BAD_REQUEST => new Response(static::DESCRIPTION_API_PLATFORM_INVALID_INPUT),
            static::STATUS_FORBIDDEN => new Response(static::DESCRIPTION_API_PLATFORM_FORBIDDEN),
            static::STATUS_NOT_FOUND => new Response(static::DESCRIPTION_API_PLATFORM_NOT_FOUND),
            static::STATUS_CONFLICT => new Response(static::DESCRIPTION_CONFLICT),
            static::STATUS_UNPROCESSABLE => new Response(static::DESCRIPTION_API_PLATFORM_ERROR_OCCURRED),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_ITEM => new PathItem(patch: $operation)]),
            [$this->createKey(static::METHOD_PATCH, static::PATH_ITEM) => new Patch(security: static::SECURITY_BACK_OFFICE_USER)],
        );

        // Act
        $responses = $this->getResponses($this->getOperation($decorator(), static::PATH_ITEM, static::METHOD_PATCH));

        // Assert
        $this->assertStringContainsString('JSON:API', (string)$responses[static::STATUS_BAD_REQUEST]->getDescription());
        $this->assertNotSame(static::DESCRIPTION_API_PLATFORM_FORBIDDEN, $responses[static::STATUS_FORBIDDEN]->getDescription());
        $this->assertSame(static::DESCRIPTION_GLUE_NOT_FOUND, $responses[static::STATUS_NOT_FOUND]->getDescription());
        $this->assertStringContainsString('901', (string)$responses[static::STATUS_UNPROCESSABLE]->getDescription());
        $this->assertSame(static::DESCRIPTION_CONFLICT, $responses[static::STATUS_CONFLICT]->getDescription());
    }

    public function testGivenDeclaredDefaultResponseWithoutContentWhenDecoratingThenItKeepsItsDescriptionAndGetsTheGlueErrorSchema(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_OK => new Response('Category returned.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
            static::RESPONSE_KEY_DEFAULT => new Response(static::DESCRIPTION_ANY_ERROR),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_ITEM => new PathItem(get: $operation)]),
            [$this->createKey(static::METHOD_GET, static::PATH_ITEM) => new Get()],
        );

        // Act
        $default = $this->getResponses($this->getOperation($decorator(), static::PATH_ITEM, static::METHOD_GET))[static::RESPONSE_KEY_DEFAULT];

        // Assert
        $this->assertSame(static::DESCRIPTION_ANY_ERROR, $default->getDescription());
        $this->assertSame(GlueApiErrorSchema::REFERENCE, $this->getSchemaReference($default, static::MIME_JSON_API));
        $this->assertArrayHasKey(ErrorResponseBuilder::EXAMPLE_METHOD_NOT_ALLOWED, $this->getExamples($default, static::MIME_JSON_API));
    }

    public function testGivenApiPlatformErrorSchemaReferencesWhenDecoratingThenTheyAreReplacedAndTheOrphanedSchemasRemoved(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_CREATED => new Response('Category created.', $this->createJsonApiContent(static::SCHEMA_RESOURCE)),
            static::STATUS_BAD_REQUEST => new Response('Invalid input', $this->createApiPlatformErrorContent(static::SCHEMA_API_PLATFORM_ERROR_JSON_API, static::SCHEMA_API_PLATFORM_ERROR)),
            static::STATUS_UNPROCESSABLE => new Response('Validation failed.', $this->createApiPlatformErrorContent(static::SCHEMA_API_PLATFORM_VIOLATION_JSON_API, static::SCHEMA_API_PLATFORM_VIOLATION)),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_COLLECTION => new PathItem(post: $operation)]),
            [$this->createKey(static::METHOD_POST, static::PATH_COLLECTION) => new Post(security: static::SECURITY_BACK_OFFICE_USER)],
        );

        // Act
        $openApi = $decorator();

        // Assert
        $badRequest = $this->getResponse($openApi, static::PATH_COLLECTION, static::METHOD_POST, static::STATUS_BAD_REQUEST);
        $this->assertSame(GlueApiErrorSchema::REFERENCE, $this->getSchemaReference($badRequest, static::MIME_JSON_API));
        $this->assertSame([static::MIME_JSON_API, static::MIME_JSON], $this->getMediaTypeNames($badRequest));

        $schemaNames = $this->getSchemaNames($openApi);
        $this->assertContains(GlueApiErrorSchema::SCHEMA_NAME, $schemaNames);
        $this->assertContains(static::SCHEMA_RESOURCE, $schemaNames);
        $this->assertNotContains(static::SCHEMA_API_PLATFORM_ERROR, $schemaNames);
        $this->assertNotContains(static::SCHEMA_API_PLATFORM_ERROR_JSON_API, $schemaNames);
        $this->assertNotContains(static::SCHEMA_API_PLATFORM_VIOLATION, $schemaNames);
        $this->assertNotContains(static::SCHEMA_API_PLATFORM_VIOLATION_JSON_API, $schemaNames);
    }

    public function testGivenHandWrittenErrorContentWhenDecoratingThenItIsKept(): void
    {
        // Arrange
        $customContent = $this->createJsonApiContent(static::SCHEMA_CUSTOM_ERROR);
        $operation = new Operation(responses: [
            static::STATUS_CONFLICT => new Response(static::DESCRIPTION_CONFLICT, $customContent),
        ]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_COLLECTION => new PathItem(post: $operation)]),
            [$this->createKey(static::METHOD_POST, static::PATH_COLLECTION) => new Post()],
        );

        // Act
        $response = $this->getResponse($decorator(), static::PATH_COLLECTION, static::METHOD_POST, static::STATUS_CONFLICT);

        // Assert
        $this->assertSame($customContent, $response->getContent());
        $this->assertContains(static::SCHEMA_CUSTOM_ERROR, $this->getSchemaNames($decorator()));
    }

    public function testGivenOperationWithoutMetadataWhenDecoratingThenDeclaredErrorsAreDocumentedAndNothingIsAdded(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_NOT_FOUND => new Response(static::DESCRIPTION_CATEGORY_NOT_FOUND),
        ]);
        $decorator = $this->createDecorator($this->createOpenApi([static::PATH_ITEM => new PathItem(get: $operation)]), []);

        // Act
        $decoratedOperation = $this->getOperation($decorator(), static::PATH_ITEM, static::METHOD_GET);

        // Assert
        $this->assertSame([static::STATUS_NOT_FOUND], array_keys($this->getResponses($decoratedOperation)));
        $this->assertSame(
            GlueApiErrorSchema::REFERENCE,
            $this->getSchemaReference($this->getResponses($decoratedOperation)[static::STATUS_NOT_FOUND], static::MIME_JSON_API),
        );
        $this->assertNull($decoratedOperation->getSecurity());
    }

    public function testGivenRequestBodySchemaWhenDecoratingThenTheValidationExampleNamesTheFirstRequiredAttribute(): void
    {
        // Arrange
        $operation = new Operation(
            responses: [static::STATUS_UNPROCESSABLE => new Response('Validation failed.')],
            requestBody: new RequestBody(content: $this->createJsonApiContent(static::SCHEMA_REQUEST)),
        );
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_COLLECTION => new PathItem(post: $operation)]),
            [$this->createKey(static::METHOD_POST, static::PATH_COLLECTION) => new Post()],
        );

        // Act
        $response = $this->getResponse($decorator(), static::PATH_COLLECTION, static::METHOD_POST, static::STATUS_UNPROCESSABLE);

        // Assert
        $example = $this->getExamples($response, static::MIME_JSON_API)[ErrorResponseBuilder::EXAMPLE_VALIDATION_FAILED];
        $this->assertSame('categoryKey => This field is missing.', $example->getValue()['errors'][0]['detail']);
    }

    public function testGivenSuccessResponseWhenDecoratingThenItIsLeftUntouched(): void
    {
        // Arrange
        $successContent = $this->createJsonApiContent(static::SCHEMA_RESOURCE);
        $operation = new Operation(responses: [static::STATUS_OK => new Response('Category returned.', $successContent)]);
        $decorator = $this->createDecorator(
            $this->createOpenApi([static::PATH_ITEM => new PathItem(get: $operation)]),
            [$this->createKey(static::METHOD_GET, static::PATH_ITEM) => new Get()],
        );

        // Act
        $response = $this->getResponse($decorator(), static::PATH_ITEM, static::METHOD_GET, static::STATUS_OK);

        // Assert
        $this->assertSame($successContent, $response->getContent());
    }

    public function testWhenDecoratingThenTheGlueErrorSchemaRequiresTheErrorsArrayAndEachErrorItsStatus(): void
    {
        // Arrange
        $decorator = $this->createDecorator($this->createOpenApi([]), []);

        // Act
        $schema = $this->getSchemas($decorator())[GlueApiErrorSchema::SCHEMA_NAME];

        // Assert
        $this->assertSame(['errors'], $schema['required']);
        $this->assertSame(['status'], $schema['properties']['errors']['items']['required']);
        $this->assertSame(
            ['code', 'status', 'detail', 'message'],
            array_keys($schema['properties']['errors']['items']['properties']),
        );
        $this->assertSame(['string', 'null'], $schema['properties']['errors']['items']['properties']['code']['type']);
        $this->assertArrayNotHasKey('nullable', $schema['properties']['errors']['items']['properties']['code']);
    }

    /**
     * @param array<string, \ApiPlatform\Metadata\HttpOperation> $httpOperationsByKey
     * @param array<string> $apiTypes
     */
    protected function createDecorator(
        OpenApi $openApi,
        array $httpOperationsByKey,
        array $apiTypes = [ApiPlatformConfig::API_TYPE_STOREFRONT],
    ): ErrorResponseOpenApiDecorator {
        $openApiFactoryMock = $this->createMock(OpenApiFactoryInterface::class);
        $openApiFactoryMock->method('__invoke')->willReturn($openApi);

        $operationMetadataResolverMock = $this->createMock(OperationMetadataResolver::class);
        $operationMetadataResolverMock->method('resolve')->willReturnCallback(
            fn (?string $operationId): ?HttpOperation => $httpOperationsByKey[$operationId] ?? null,
        );

        $errorResponseBuilder = new ErrorResponseBuilder(
            new ApiPlatformConfig([], '', '', $apiTypes, false),
            new ProviderNotFoundErrorResolver(),
            static::ERROR_FORMATS,
        );
        $schemaReferenceResolver = new SchemaReferenceResolver();
        $pathItemOperationAccessor = new PathItemOperationAccessor();

        return new ErrorResponseOpenApiDecorator(
            $openApiFactoryMock,
            $operationMetadataResolverMock,
            $pathItemOperationAccessor,
            new DefaultErrorResponseAdder($errorResponseBuilder),
            new RequestAttributesResolver($schemaReferenceResolver),
            new ErrorResponseDocumenter($errorResponseBuilder, $schemaReferenceResolver, static::ERROR_FORMATS),
            new ApiPlatformErrorSchemaRemover($pathItemOperationAccessor, $schemaReferenceResolver),
            new GlueApiErrorSchema(),
        );
    }

    /**
     * @param array<string, \ApiPlatform\OpenApi\Model\PathItem> $pathItems
     */
    protected function createOpenApi(array $pathItems): OpenApi
    {
        $paths = new Paths();
        $pathItemOperationAccessor = new PathItemOperationAccessor();

        foreach ($pathItems as $path => $pathItem) {
            foreach ($pathItemOperationAccessor->getOperations($pathItem) as $method => $operation) {
                $pathItem = $pathItemOperationAccessor->withOperation($pathItem, $method, $operation->withOperationId($this->createKey($method, $path)));
            }

            $paths->addPath($path, $pathItem);
        }

        return new OpenApi(new Info('Spryker Glue API', '0.0.0'), [], $paths, new Components(schemas: $this->createSchemas()));
    }

    /**
     * @return \ArrayObject<string, \ApiPlatform\OpenApi\Model\Schema>
     */
    protected function createSchemas(): ArrayObject
    {
        $requestAttributes = [
            'properties' => ['isActive' => ['type' => 'boolean'], static::ATTRIBUTE_CATEGORY_KEY => ['type' => 'string']],
            'required' => [static::ATTRIBUTE_CATEGORY_KEY, 'templateName'],
        ];

        return new ArrayObject([
            static::SCHEMA_RESOURCE => $this->createSchema(['properties' => ['data' => ['properties' => ['type' => ['type' => 'string']]]]]),
            static::SCHEMA_REQUEST => $this->createSchema(['properties' => ['data' => ['properties' => ['attributes' => $requestAttributes]]]]),
            static::SCHEMA_CUSTOM_ERROR => $this->createSchema(['properties' => ['errors' => ['type' => 'array']]]),
            static::SCHEMA_API_PLATFORM_ERROR => $this->createSchema(['type' => 'object', 'properties' => ['title' => ['type' => 'string']]]),
            static::SCHEMA_API_PLATFORM_ERROR_JSON_API => $this->createSchema(['properties' => ['errors' => ['items' => ['allOf' => [['$ref' => static::REFERENCE_PREFIX . static::SCHEMA_API_PLATFORM_ERROR]]]]]]),
            static::SCHEMA_API_PLATFORM_VIOLATION => $this->createSchema(['type' => 'object', 'properties' => ['detail' => ['type' => 'string']]]),
            static::SCHEMA_API_PLATFORM_VIOLATION_JSON_API => $this->createSchema(['properties' => ['data' => ['type' => 'object']]]),
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     */
    protected function createSchema(array $definition): Schema
    {
        $schema = new Schema();
        $schema->exchangeArray($definition);

        return $schema;
    }

    /**
     * @return \ArrayObject<string, \ApiPlatform\OpenApi\Model\MediaType>
     */
    protected function createJsonApiContent(string $schemaName): ArrayObject
    {
        return new ArrayObject([
            static::MIME_JSON_API => new MediaType(schema: new ArrayObject(['$ref' => static::REFERENCE_PREFIX . $schemaName])),
        ]);
    }

    /**
     * @return \ArrayObject<string, \ApiPlatform\OpenApi\Model\MediaType>
     */
    protected function createApiPlatformErrorContent(string $jsonApiSchemaName, string $plainSchemaName): ArrayObject
    {
        return new ArrayObject([
            static::MIME_JSON_API => new MediaType(schema: new ArrayObject(['$ref' => static::REFERENCE_PREFIX . $jsonApiSchemaName])),
            static::MIME_PROBLEM_JSON => new MediaType(schema: new ArrayObject(['$ref' => static::REFERENCE_PREFIX . $plainSchemaName])),
            static::MIME_JSON => new MediaType(schema: new ArrayObject(['$ref' => static::REFERENCE_PREFIX . $plainSchemaName])),
        ]);
    }

    protected function createKey(string $method, string $path): string
    {
        return sprintf('%s %s', strtolower($method), $path);
    }

    /**
     * @return array<int|string, \ApiPlatform\OpenApi\Model\Response>
     */
    protected function getResponses(Operation $operation): array
    {
        return $operation->getResponses() ?? [];
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    protected function getSchemas(OpenApi $openApi): ArrayObject
    {
        return $openApi->getComponents()->getSchemas() ?? new ArrayObject();
    }

    /**
     * @return array<string>
     */
    protected function getSchemaNames(OpenApi $openApi): array
    {
        return array_keys($this->getSchemas($openApi)->getArrayCopy());
    }

    /**
     * @return array<string>
     */
    protected function getMediaTypeNames(Response $response): array
    {
        return array_keys(($response->getContent() ?? new ArrayObject())->getArrayCopy());
    }

    protected function getOperation(OpenApi $openApi, string $path, string $method): Operation
    {
        $pathItem = $openApi->getPaths()->getPath($path);
        $this->assertNotNull($pathItem);

        $operation = $pathItem->{sprintf('get%s', ucfirst($method))}();
        $this->assertInstanceOf(Operation::class, $operation);

        return $operation;
    }

    protected function getResponse(OpenApi $openApi, string $path, string $method, int $status): Response
    {
        $response = $this->getOperation($openApi, $path, $method)->getResponses()[$status] ?? null;
        $this->assertInstanceOf(Response::class, $response);

        return $response;
    }

    protected function getSchemaReference(Response $response, string $mimeType): ?string
    {
        $mediaType = $response->getContent()[$mimeType] ?? null;
        $this->assertInstanceOf(MediaType::class, $mediaType);

        return $mediaType->getSchema()['$ref'] ?? null;
    }

    /**
     * @return array<string, \ApiPlatform\OpenApi\Model\Example>
     */
    protected function getExamples(Response $response, string $mimeType): array
    {
        $mediaType = $response->getContent()[$mimeType] ?? null;
        $this->assertInstanceOf(MediaType::class, $mediaType);

        return $mediaType->getExamples()?->getArrayCopy() ?? [];
    }
}
