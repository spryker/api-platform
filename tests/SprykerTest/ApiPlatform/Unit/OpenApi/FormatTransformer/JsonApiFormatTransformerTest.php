<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\OpenApi\FormatTransformer;

use ArrayObject;
use Codeception\Test\Unit;
use Spryker\ApiPlatform\OpenApi\FormatTransformer\JsonApiFormatTransformer;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group OpenApi
 * @group FormatTransformer
 * @group JsonApiFormatTransformerTest
 * Add your own group annotations below this line
 */
class JsonApiFormatTransformerTest extends Unit
{
    protected const string SCHEMA_JSON_API = 'customers.jsonapi';

    protected const string SCHEMA_PLAIN = 'customers';

    protected const string RESOURCE_SHORT_NAME = 'customers';

    protected ApiUnitTester $tester;

    public function testGivenAJsonApiSchemaWhenTransformingThenTheTypeCarriesTheResourceNameAsExample(): void
    {
        // Arrange
        $schemas = $this->createSchemas();

        // Act
        $schemas = (new JsonApiFormatTransformer())->transformSchemas($schemas);

        // Assert
        $this->assertSame(
            static::RESOURCE_SHORT_NAME,
            $schemas[static::SCHEMA_JSON_API]['properties']['data']['properties']['type']['example'],
        );
    }

    public function testGivenANonJsonApiSchemaWhenTransformingThenItIsLeftUntouched(): void
    {
        // Arrange
        $schemas = $this->createSchemas();
        $before = $schemas[static::SCHEMA_PLAIN];

        // Act
        $schemas = (new JsonApiFormatTransformer())->transformSchemas($schemas);

        // Assert
        $this->assertSame($before, $schemas[static::SCHEMA_PLAIN]);
    }

    public function testGivenAnyReferenceWhenFixingThenItIsReturnedUnchanged(): void
    {
        // Arrange — request bodies are produced by JsonApiInputSchemaFactory, not corrected here.
        $ref = '#/components/schemas/customers.jsonapi-patch';

        // Act
        $result = (new JsonApiFormatTransformer())->fixRequestBodyReference($ref, 'patch');

        // Assert
        $this->assertSame($ref, $result);
    }

    /**
     * @return \ArrayObject<string, array<string, mixed>>
     */
    protected function createSchemas(): ArrayObject
    {
        return new ArrayObject([
            static::SCHEMA_PLAIN => ['properties' => ['email' => ['type' => 'string']]],
            static::SCHEMA_JSON_API => [
                'properties' => [
                    'data' => [
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'attributes' => ['properties' => ['email' => ['type' => 'string']]],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
