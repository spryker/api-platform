<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\ErrorResponse;

use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ArrayObject;
use Codeception\Test\Unit;
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
 * @group ErrorResponse
 * @group RequestAttributesResolverTest
 * Add your own group annotations below this line
 */
class RequestAttributesResolverTest extends Unit
{
    protected const string SCHEMA_NAME_REQUEST = 'categories.jsonapi-post';

    protected const string MIME_JSON_API = 'application/vnd.api+json';

    protected const string ATTRIBUTE_CATEGORY_KEY = 'categoryKey';

    protected const string ATTRIBUTE_TEMPLATE_NAME = 'templateName';

    protected const string ATTRIBUTE_IS_ACTIVE = 'isActive';

    protected ApiUnitTester $tester;

    public function testGivenRequestSchemaWhenResolvingThenRequiredAttributesComeFirstAndEveryAttributeOnce(): void
    {
        // Arrange
        $attributesSchema = [
            'properties' => [
                static::ATTRIBUTE_IS_ACTIVE => ['type' => 'boolean'],
                static::ATTRIBUTE_CATEGORY_KEY => ['type' => 'string'],
            ],
            'required' => [static::ATTRIBUTE_CATEGORY_KEY, static::ATTRIBUTE_TEMPLATE_NAME],
        ];
        $schemas = new ArrayObject([
            static::SCHEMA_NAME_REQUEST => new ArrayObject([
                'properties' => [
                    'data' => [
                        'properties' => ['attributes' => $attributesSchema],
                    ],
                ],
            ]),
        ]);
        $operation = new Operation(requestBody: new RequestBody(content: new ArrayObject([
            static::MIME_JSON_API => new MediaType(schema: (new SchemaReferenceResolver())->createSchemaReference('#/components/schemas/' . static::SCHEMA_NAME_REQUEST)),
        ])));

        // Act
        $attributes = (new RequestAttributesResolver(new SchemaReferenceResolver()))->resolve($operation, $schemas);

        // Assert
        $this->assertSame([static::ATTRIBUTE_CATEGORY_KEY, static::ATTRIBUTE_TEMPLATE_NAME, static::ATTRIBUTE_IS_ACTIVE], $attributes);
    }

    public function testGivenOperationWithoutRequestBodyWhenResolvingThenNoAttributesAreReturned(): void
    {
        // Act
        $attributes = (new RequestAttributesResolver(new SchemaReferenceResolver()))->resolve(new Operation(), new ArrayObject());

        // Assert
        $this->assertSame([], $attributes);
    }
}
