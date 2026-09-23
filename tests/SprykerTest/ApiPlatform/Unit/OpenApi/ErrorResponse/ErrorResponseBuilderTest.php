<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Example;
use ArrayObject;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseBuilder;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ProviderNotFoundErrorResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;
use SprykerTest\ApiPlatform\Fixture\ErrorResponseFixtureProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group ErrorResponse
 * @group ErrorResponseBuilderTest
 * Add your own group annotations below this line
 */
class ErrorResponseBuilderTest extends Unit
{
    protected const string SECURITY_CODE = '411';

    protected const string SECURITY_MESSAGE_AGENT = 'Action is available to agent user only.';

    protected const string SECURITY_MESSAGE_EXTRA_PROPERTY = 'Only an extra property.';

    protected const string SECURITY_BACK_OFFICE_USER = "is_granted('ROLE_BACK_OFFICE_USER')";

    protected const string ATTRIBUTE_CATEGORY_KEY = 'categoryKey';

    protected const string NOT_FOUND_CODE_CUSTOMER = '1201';

    protected const string NOT_FOUND_MESSAGE_CUSTOMER = 'Customer with reference "{customerReference}" was not found.';

    protected const string ERROR_CODE_UNSUPPORTED_FILTER_FORMAT = '011';

    protected const string ERROR_CODE_RESOURCE_NOT_FOUND = '007';

    protected const string MESSAGE_RESOURCE_NOT_FOUND = 'Not found';

    protected const string ERROR_CODE_BAD_REQUEST = '400';

    protected const string MESSAGE_UNSUPPORTED_FILTER_FORMAT = 'Unsupported `Filter` format is used. Please use `filter[resource.property]`';

    protected const string DETAIL_POST_DATA_MISSING = 'Post data missing or invalid.';

    protected const string DETAIL_METHOD_NOT_ALLOWED = 'Method Not Allowed';

    protected const string DETAIL_NOT_ACCEPTABLE = 'Requested format "text/plain" is not supported. Supported MIME types are "application/vnd.api+json", "application/json", "application/ld+json".';

    protected const string DETAIL_UNSUPPORTED_MEDIA_TYPE = 'The content-type "text/plain" is not supported. Supported MIME types are "application/vnd.api+json", "application/xml", "text/xml".';

    protected const string FILTER_FORM = 'filter[resource.property]';

    protected const string JSON_API = 'JSON:API';

    protected const string STATUS_UNSUPPORTED_MEDIA_TYPE_TEXT = '415';

    protected const int STATUS_TEAPOT = 418;

    protected const string DETAIL_TEAPOT = "I'm a teapot";

    /**
     * @var array<string, array<string>>
     */
    protected const array FORMATS = [
        'jsonapi' => ['application/vnd.api+json', 'application/json'],
        'jsonld' => ['application/ld+json', 'application/json'],
    ];

    /**
     * @var array<string, array<string>>
     */
    protected const array INPUT_FORMATS = [
        'jsonapi' => ['application/vnd.api+json'],
        'xml' => ['application/xml', 'text/xml'],
    ];

    protected ApiUnitTester $tester;

    public function testGivenBackendApiWhenBuildingUnauthorizedExamplesThenMissingAndInvalidTokenAreBothShown(): void
    {
        // Act
        $examples = $this->createBackendBuilder()->buildExamples(Response::HTTP_UNAUTHORIZED);

        // Assert
        $this->assertSame(
            [ErrorResponseBuilder::EXAMPLE_MISSING_ACCESS_TOKEN, ErrorResponseBuilder::EXAMPLE_INVALID_ACCESS_TOKEN],
            array_keys($examples->getArrayCopy()),
        );
        $this->assertSame(
            ['errors' => [['status' => Response::HTTP_UNAUTHORIZED, 'detail' => 'Authorization header is required']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_MISSING_ACCESS_TOKEN),
        );
    }

    public function testGivenStorefrontApiWhenBuildingUnauthorizedExamplesThenOnlyTheInvalidTokenBodyIsShown(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_UNAUTHORIZED);

        // Assert
        $this->assertSame([ErrorResponseBuilder::EXAMPLE_INVALID_ACCESS_TOKEN], array_keys($examples->getArrayCopy()));
        $this->assertSame(
            ['errors' => [['code' => '001', 'status' => Response::HTTP_UNAUTHORIZED, 'message' => 'Invalid access token.']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_INVALID_ACCESS_TOKEN),
        );
    }

    public function testGivenPublicOperationWhenBuildingUnauthorizedAndForbiddenExamplesThenNoBearerExampleIsShown(): void
    {
        // Arrange
        $operation = new Post();

        // Act
        $unauthorizedExamples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_UNAUTHORIZED, $operation);
        $forbiddenExamples = $this->createBackendBuilder()->buildExamples(Response::HTTP_FORBIDDEN, $operation);

        // Assert
        $this->assertSame([], $unauthorizedExamples->getArrayCopy());
        $this->assertSame([], $forbiddenExamples->getArrayCopy());
    }

    public function testGivenPublicOperationWithSecurityCodeWhenBuildingForbiddenExamplesThenOnlyTheResourceDenialIsShown(): void
    {
        // Arrange
        $operation = new Get(extraProperties: ['securityCode' => static::SECURITY_CODE]);

        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_FORBIDDEN, $operation);

        // Assert
        $this->assertSame([ErrorResponseBuilder::EXAMPLE_FORBIDDEN], array_keys($examples->getArrayCopy()));
        $this->assertSame(static::SECURITY_CODE, $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_FORBIDDEN)['errors'][0]['code']);
    }

    public function testGivenStorefrontApiWhenBuildingForbiddenExamplesThenMissingTokenAndGenericDenialAreShown(): void
    {
        // Arrange
        $operation = new Get(security: static::SECURITY_BACK_OFFICE_USER);

        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_FORBIDDEN, $operation);

        // Assert
        $this->assertSame(
            [ErrorResponseBuilder::EXAMPLE_MISSING_ACCESS_TOKEN, ErrorResponseBuilder::EXAMPLE_FORBIDDEN],
            array_keys($examples->getArrayCopy()),
        );
        $this->assertSame(
            ['errors' => [['code' => '002', 'status' => Response::HTTP_FORBIDDEN, 'detail' => 'Missing access token.']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_MISSING_ACCESS_TOKEN),
        );
        $this->assertSame(
            ['errors' => [['code' => '802', 'status' => Response::HTTP_FORBIDDEN, 'detail' => 'Unauthorized request.', 'message' => 'Unauthorized request.']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_FORBIDDEN),
        );
    }

    public function testGivenResourceSecurityCodeWhenBuildingForbiddenExamplesThenTheDenialUsesTheResourceCode(): void
    {
        // Arrange
        $operation = new Get(security: static::SECURITY_BACK_OFFICE_USER, extraProperties: ['securityCode' => static::SECURITY_CODE]);

        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_FORBIDDEN, $operation);

        // Assert
        $this->assertSame(
            ['errors' => [['code' => static::SECURITY_CODE, 'status' => Response::HTTP_FORBIDDEN, 'detail' => 'Unauthorized request.', 'message' => 'Unauthorized request']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_FORBIDDEN),
        );
    }

    public function testGivenResourceSecurityMessageWhenBuildingUnauthorizedAndForbiddenExamplesThenTheDenialUsesIt(): void
    {
        // Arrange
        $operation = new Get(
            security: static::SECURITY_BACK_OFFICE_USER,
            securityMessage: static::SECURITY_MESSAGE_AGENT,
            extraProperties: ['securityCode' => static::SECURITY_CODE, 'securityMessage' => static::SECURITY_MESSAGE_EXTRA_PROPERTY],
        );
        $builder = $this->createStorefrontBuilder();

        // Act
        $unauthorizedExamples = $builder->buildExamples(Response::HTTP_UNAUTHORIZED, $operation);
        $forbiddenExamples = $builder->buildExamples(Response::HTTP_FORBIDDEN, $operation);

        // Assert
        $expectedError = [
            'code' => static::SECURITY_CODE,
            'status' => Response::HTTP_FORBIDDEN,
            'detail' => static::SECURITY_MESSAGE_AGENT,
            'message' => rtrim(static::SECURITY_MESSAGE_AGENT, '.'),
        ];
        $this->assertSame(['errors' => [$expectedError]], $this->getExampleValue($forbiddenExamples, ErrorResponseBuilder::EXAMPLE_FORBIDDEN));
        $this->assertSame(
            ['errors' => [array_replace($expectedError, ['status' => Response::HTTP_UNAUTHORIZED])]],
            $this->getExampleValue($unauthorizedExamples, ErrorResponseBuilder::EXAMPLE_UNAUTHENTICATED),
        );
    }

    public function testGivenBackendApiWhenBuildingForbiddenExamplesThenTheGenericAndTheAclDenialAreShown(): void
    {
        // Arrange
        $operation = new Get(security: static::SECURITY_BACK_OFFICE_USER);

        // Act
        $examples = $this->createBackendBuilder()->buildExamples(Response::HTTP_FORBIDDEN, $operation);

        // Assert
        $this->assertSame(
            [ErrorResponseBuilder::EXAMPLE_FORBIDDEN, ErrorResponseBuilder::EXAMPLE_ACCESS_DENIED_BY_ACL],
            array_keys($examples->getArrayCopy()),
        );
        $this->assertSame(
            ['errors' => [['code' => '802', 'status' => Response::HTTP_FORBIDDEN, 'detail' => 'Unauthorized request.', 'message' => 'Unauthorized request.']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_FORBIDDEN),
        );
        $this->assertSame(
            ['errors' => [['status' => Response::HTTP_FORBIDDEN, 'detail' => 'Access denied by ACL rules']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_ACCESS_DENIED_BY_ACL),
        );
    }

    public function testGivenBodyOperationWhenBuildingBadRequestExamplesThenTheBodyRejectionsAndTheFilterRejectionAreShown(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_BAD_REQUEST, new Post());

        // Assert
        $this->assertSame(
            [
                ErrorResponseBuilder::EXAMPLE_INVALID_POST_DATA,
                ErrorResponseBuilder::EXAMPLE_INVALID_TYPE,
                ErrorResponseBuilder::EXAMPLE_POST_DATA_MISSING,
                ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_FILTER_FORMAT,
            ],
            array_keys($examples->getArrayCopy()),
        );
        $this->assertSame(
            ['errors' => [['status' => Response::HTTP_BAD_REQUEST, 'detail' => 'Post data is invalid.', 'message' => 'Post data is invalid.']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_INVALID_POST_DATA),
        );
        $this->assertSame(
            ['errors' => [['status' => Response::HTTP_BAD_REQUEST, 'detail' => 'Invalid type.', 'message' => 'Invalid type.']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_INVALID_TYPE),
        );
        $this->assertSame(
            ['errors' => [['code' => static::ERROR_CODE_BAD_REQUEST, 'status' => Response::HTTP_BAD_REQUEST, 'detail' => static::DETAIL_POST_DATA_MISSING]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_POST_DATA_MISSING),
        );
    }

    public function testGivenReadOperationWhenBuildingBadRequestExamplesThenOnlyTheFilterFormatRejectionIsShown(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_BAD_REQUEST, new GetCollection());

        // Assert
        $this->assertSame([ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_FILTER_FORMAT], array_keys($examples->getArrayCopy()));
        $this->assertSame(
            [
            'errors' => [[
                'code' => static::ERROR_CODE_UNSUPPORTED_FILTER_FORMAT,
                'status' => Response::HTTP_BAD_REQUEST,
                'message' => static::MESSAGE_UNSUPPORTED_FILTER_FORMAT,
            ]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_FILTER_FORMAT),
        );
    }

    public function testGivenBodyLessWriteOperationWhenBuildingBadRequestExamplesThenOnlyTheFilterFormatRejectionIsShown(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_BAD_REQUEST, new Delete());

        // Assert
        $this->assertSame([ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_FILTER_FORMAT], array_keys($examples->getArrayCopy()));
    }

    public function testGivenEachMethodWhenBuildingTheBadRequestDescriptionThenItNamesWhatTheMethodRejects(): void
    {
        // Arrange
        $builder = $this->createStorefrontBuilder();

        // Act
        $readDescription = $builder->buildDescription(Response::HTTP_BAD_REQUEST, new Get());
        $writeDescription = $builder->buildDescription(Response::HTTP_BAD_REQUEST, new Post());

        // Assert
        $this->assertStringContainsString(static::FILTER_FORM, $readDescription);
        $this->assertStringNotContainsString(static::JSON_API, $readDescription);
        $this->assertStringContainsString(static::FILTER_FORM, $writeDescription);
        $this->assertStringContainsString(static::JSON_API, $writeDescription);
    }

    public function testWhenBuildingTheValidationDescriptionThenItNamesTheCodeAndTheDetailForm(): void
    {
        // Act
        $description = $this->createStorefrontBuilder()->buildDescription(Response::HTTP_UNPROCESSABLE_ENTITY, new Patch());

        // Assert
        $this->assertStringContainsString('901', $description);
        $this->assertStringContainsString('attribute', $description);
    }

    public function testGivenBodyLessOperationWhenBuildingDefaultExamplesThenTheMethodAndFormatRejectionsAreShown(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildDefaultExamples(new Get(inputFormats: static::INPUT_FORMATS));

        // Assert
        $this->assertSame(
            [ErrorResponseBuilder::EXAMPLE_METHOD_NOT_ALLOWED, ErrorResponseBuilder::EXAMPLE_NOT_ACCEPTABLE],
            array_keys($examples->getArrayCopy()),
        );
        $this->assertSame(
            ['errors' => [['status' => Response::HTTP_METHOD_NOT_ALLOWED, 'detail' => static::DETAIL_METHOD_NOT_ALLOWED]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_METHOD_NOT_ALLOWED),
        );
        $this->assertSame(
            ['errors' => [['status' => Response::HTTP_NOT_ACCEPTABLE, 'detail' => static::DETAIL_NOT_ACCEPTABLE]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_NOT_ACCEPTABLE),
        );
    }

    public function testGivenBodyOperationWhenBuildingDefaultExamplesThenTheContentTypeRejectionListsItsInputFormats(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildDefaultExamples(new Post(inputFormats: static::INPUT_FORMATS));

        // Assert
        $this->assertSame(
            [
                ErrorResponseBuilder::EXAMPLE_METHOD_NOT_ALLOWED,
                ErrorResponseBuilder::EXAMPLE_NOT_ACCEPTABLE,
                ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_MEDIA_TYPE,
            ],
            array_keys($examples->getArrayCopy()),
        );
        $this->assertSame(
            ['errors' => [['status' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'detail' => static::DETAIL_UNSUPPORTED_MEDIA_TYPE]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_MEDIA_TYPE),
        );
    }

    public function testGivenBodyOperationWithoutInputFormatsWhenBuildingDefaultExamplesThenNoContentTypeRejectionIsShown(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildDefaultExamples(new Post());

        // Assert
        $this->assertArrayNotHasKey(ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_MEDIA_TYPE, $examples->getArrayCopy());
    }

    /**
     * API Platform negotiates the Content-Type only while deserializing, so an operation reading its body itself
     * never answers 415.
     */
    public function testGivenBodyOperationThatIsNotDeserializedWhenBuildingDefaultsThenNoContentTypeRejectionIsShown(): void
    {
        // Arrange
        $operation = new Post(inputFormats: static::INPUT_FORMATS, deserialize: false);
        $builder = $this->createStorefrontBuilder();

        // Act
        $examples = $builder->buildDefaultExamples($operation);
        $description = $builder->buildDefaultDescription($operation);

        // Assert
        $this->assertArrayNotHasKey(ErrorResponseBuilder::EXAMPLE_UNSUPPORTED_MEDIA_TYPE, $examples->getArrayCopy());
        $this->assertStringNotContainsString(static::STATUS_UNSUPPORTED_MEDIA_TYPE_TEXT, $description);
    }

    public function testGivenEachMethodWhenBuildingTheDefaultDescriptionThenItNamesTheStatusesItCovers(): void
    {
        // Arrange
        $builder = $this->createStorefrontBuilder();

        // Act
        $readDescription = $builder->buildDefaultDescription(new Get(inputFormats: static::INPUT_FORMATS));
        $writeDescription = $builder->buildDefaultDescription(new Post(inputFormats: static::INPUT_FORMATS));

        // Assert
        $this->assertStringContainsString('405', $readDescription);
        $this->assertStringContainsString('406', $readDescription);
        $this->assertStringNotContainsString(static::STATUS_UNSUPPORTED_MEDIA_TYPE_TEXT, $readDescription);
        $this->assertStringContainsString(static::STATUS_UNSUPPORTED_MEDIA_TYPE_TEXT, $writeDescription);
    }

    public function testGivenProviderWithNotFoundConstantsWhenBuildingNotFoundExampleThenTheResourceCodeAndMessageAreUsed(): void
    {
        // Arrange
        $operation = new Get(provider: ErrorResponseFixtureProvider::class);

        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_NOT_FOUND, $operation);

        // Assert
        $this->assertSame(
            [
            'errors' => [[
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => ErrorResponseFixtureProvider::ERROR_MESSAGE_CATEGORY_NOT_FOUND,
                'message' => ErrorResponseFixtureProvider::ERROR_MESSAGE_CATEGORY_NOT_FOUND,
                'code' => ErrorResponseFixtureProvider::ERROR_CODE_CATEGORY_NOT_FOUND,
            ]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_NOT_FOUND),
        );
    }

    public function testGivenDeclaredNotFoundPairWhenBuildingNotFoundExampleThenTheDeclaredCodeAndMessageAreUsed(): void
    {
        // Arrange
        $operation = new Get(provider: ErrorResponseFixtureProvider::class, extraProperties: [
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_CODE => static::NOT_FOUND_CODE_CUSTOMER,
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_MESSAGE => static::NOT_FOUND_MESSAGE_CUSTOMER,
        ]);

        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_NOT_FOUND, $operation);

        // Assert
        $this->assertSame(
            [
            'errors' => [[
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => static::NOT_FOUND_MESSAGE_CUSTOMER,
                'message' => static::NOT_FOUND_MESSAGE_CUSTOMER,
                'code' => static::NOT_FOUND_CODE_CUSTOMER,
            ]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_NOT_FOUND),
        );
    }

    /**
     * The Backend API documents one resource-not-found body for every operation, so a declared pair or provider
     * constants are deliberately not consulted there.
     */
    public function testGivenBackendApiWhenBuildingNotFoundExampleThenTheSingleResourceNotFoundBodyIsUsed(): void
    {
        // Arrange
        $operation = new Get(provider: ErrorResponseFixtureProvider::class, extraProperties: [
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_CODE => static::NOT_FOUND_CODE_CUSTOMER,
            ProviderNotFoundErrorResolver::EXTRA_PROPERTY_NOT_FOUND_MESSAGE => static::NOT_FOUND_MESSAGE_CUSTOMER,
        ]);

        // Act
        $examples = $this->createBackendBuilder()->buildExamples(Response::HTTP_NOT_FOUND, $operation);

        // Assert
        $this->assertSame(
            ['errors' => [['code' => static::ERROR_CODE_RESOURCE_NOT_FOUND, 'status' => Response::HTTP_NOT_FOUND, 'detail' => static::MESSAGE_RESOURCE_NOT_FOUND, 'message' => static::MESSAGE_RESOURCE_NOT_FOUND]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_NOT_FOUND),
        );
    }

    public function testGivenNoProviderWhenBuildingNotFoundExampleThenTheGenericBodyIsUsed(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_NOT_FOUND);

        // Assert
        $this->assertSame(
            ['errors' => [['status' => Response::HTTP_NOT_FOUND, 'detail' => 'Not Found']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_NOT_FOUND),
        );
    }

    public function testGivenRequiredAttributesWhenBuildingValidationExampleThenTheFirstOneIsReportedMissing(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            new Patch(),
            [static::ATTRIBUTE_CATEGORY_KEY, 'templateName'],
        );

        // Assert
        $this->assertSame(
            ['errors' => [['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'categoryKey => This field is missing.']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_VALIDATION_FAILED),
        );
    }

    public function testGivenNoAttributesWhenBuildingValidationExampleThenAGenericViolationIsShown(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Assert
        $this->assertSame(
            ['errors' => [['code' => '901', 'status' => Response::HTTP_UNPROCESSABLE_ENTITY, 'detail' => 'This value is not valid.']]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_VALIDATION_FAILED),
        );
    }

    public function testGivenAnyOtherStatusWhenBuildingExamplesThenTheHttpReasonPhraseIsTheDetail(): void
    {
        // Act
        $examples = $this->createStorefrontBuilder()->buildExamples(static::STATUS_TEAPOT);

        // Assert
        $this->assertSame(
            ['errors' => [['status' => static::STATUS_TEAPOT, 'detail' => static::DETAIL_TEAPOT]]],
            $this->getExampleValue($examples, ErrorResponseBuilder::EXAMPLE_ERROR),
        );
    }

    public function testGivenEachApiWhenBuildingTheUnauthorizedDescriptionThenItNamesWhatThatApiRejects(): void
    {
        // Act
        $backendDescription = $this->createBackendBuilder()->buildDescription(Response::HTTP_UNAUTHORIZED);
        $storefrontDescription = $this->createStorefrontBuilder()->buildDescription(Response::HTTP_UNAUTHORIZED);

        // Assert
        $this->assertStringContainsString('Missing', $backendDescription);
        $this->assertStringNotContainsString('Missing', $storefrontDescription);
    }

    public function testGivenSecurityExpressionOrBearerRequirementWhenCheckingProtectionThenTheOperationIsProtected(): void
    {
        // Arrange
        $builder = $this->createStorefrontBuilder();

        // Act & Assert
        $this->assertTrue($builder->isProtectedOperation(new Get(security: static::SECURITY_BACK_OFFICE_USER)));
        $this->assertTrue($builder->isProtectedOperation(new Get(extraProperties: ['securityBearerAuthRequired' => true])));
        $this->assertFalse($builder->isProtectedOperation(new Get()));
    }

    protected function createBackendBuilder(): ErrorResponseBuilder
    {
        return $this->createBuilder([ApiPlatformConfig::API_TYPE_BACKEND]);
    }

    protected function createStorefrontBuilder(): ErrorResponseBuilder
    {
        return $this->createBuilder([ApiPlatformConfig::API_TYPE_STOREFRONT]);
    }

    /**
     * @param array<string> $apiTypes
     */
    protected function createBuilder(array $apiTypes): ErrorResponseBuilder
    {
        return new ErrorResponseBuilder(
            new ApiPlatformConfig([], '', '', $apiTypes, false),
            new ProviderNotFoundErrorResolver(),
            static::FORMATS,
        );
    }

    /**
     * @param \ArrayObject<string, \ApiPlatform\OpenApi\Model\Example> $examples
     *
     * @return array<string, mixed>
     */
    protected function getExampleValue(ArrayObject $examples, string $name): array
    {
        $example = $examples[$name] ?? null;
        $this->assertInstanceOf(Example::class, $example);

        return $example->getValue();
    }
}
