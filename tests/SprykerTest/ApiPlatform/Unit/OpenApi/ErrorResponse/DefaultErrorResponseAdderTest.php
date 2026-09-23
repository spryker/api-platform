<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\DefaultErrorResponseAdder;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseBuilder;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ErrorResponseDocumenter;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ProviderNotFoundErrorResolver;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group ErrorResponse
 * @group DefaultErrorResponseAdderTest
 * Add your own group annotations below this line
 */
class DefaultErrorResponseAdderTest extends Unit
{
    protected const string SECURITY_BACK_OFFICE_USER = "is_granted('ROLE_BACK_OFFICE_USER')";

    protected const string DESCRIPTION_DECLARED_NOT_FOUND = 'Category not found.';

    protected const int STATUS_OK = 200;

    protected const int STATUS_CREATED = 201;

    protected const int STATUS_BAD_REQUEST = 400;

    protected const int STATUS_UNAUTHORIZED = 401;

    protected const int STATUS_FORBIDDEN = 403;

    protected const int STATUS_NOT_FOUND = 404;

    /**
     * @var array<string, array<string>>
     */
    protected const array FORMATS = ['jsonapi' => ['application/vnd.api+json']];

    protected ApiUnitTester $tester;

    public function testGivenProtectedItemOperationWhenAddingThenTheBearerAndNotFoundResponsesJoinTheDefault(): void
    {
        // Arrange
        $operation = new Operation(responses: [static::STATUS_OK => new Response('Category returned.')]);

        // Act
        $operation = $this->createAdder()->add($operation, new Get(security: static::SECURITY_BACK_OFFICE_USER));

        // Assert
        $this->assertSame(
            [static::STATUS_OK, static::STATUS_BAD_REQUEST, static::STATUS_UNAUTHORIZED, static::STATUS_FORBIDDEN, static::STATUS_NOT_FOUND, ErrorResponseDocumenter::RESPONSE_KEY_DEFAULT],
            array_keys($operation->getResponses() ?? []),
        );
        $this->assertNull($operation->getSecurity());
    }

    public function testGivenPublicCollectionOperationWhenAddingThenOnlyBadRequestAndDefaultJoinAndSecurityIsCleared(): void
    {
        // Arrange
        $operation = new Operation(responses: [static::STATUS_OK => new Response('Categories returned.')]);

        // Act
        $operation = $this->createAdder()->add($operation, new GetCollection());

        // Assert
        $this->assertSame([static::STATUS_OK, static::STATUS_BAD_REQUEST, ErrorResponseDocumenter::RESPONSE_KEY_DEFAULT], array_keys($operation->getResponses() ?? []));
        $this->assertSame([], $operation->getSecurity());
    }

    public function testGivenDeclaredResponseWhenAddingThenItsDescriptionIsKeptAndPostGetsNoNotFound(): void
    {
        // Arrange
        $operation = new Operation(responses: [
            static::STATUS_CREATED => new Response('Category created.'),
            static::STATUS_NOT_FOUND => new Response(static::DESCRIPTION_DECLARED_NOT_FOUND),
        ]);

        // Act
        $operation = $this->createAdder()->add($operation, new Post(security: static::SECURITY_BACK_OFFICE_USER));

        // Assert
        $responses = $operation->getResponses() ?? [];
        $this->assertSame([static::STATUS_CREATED, static::STATUS_NOT_FOUND, static::STATUS_BAD_REQUEST, static::STATUS_UNAUTHORIZED, static::STATUS_FORBIDDEN, ErrorResponseDocumenter::RESPONSE_KEY_DEFAULT], array_keys($responses));
        $this->assertSame(static::DESCRIPTION_DECLARED_NOT_FOUND, $responses[static::STATUS_NOT_FOUND]->getDescription());
    }

    protected function createAdder(): DefaultErrorResponseAdder
    {
        return new DefaultErrorResponseAdder(new ErrorResponseBuilder(
            new ApiPlatformConfig([], '', '', [ApiPlatformConfig::API_TYPE_STOREFRONT], false),
            new ProviderNotFoundErrorResolver(),
            static::FORMATS,
        ));
    }
}
