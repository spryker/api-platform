<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\MediaType\MediaTypeFormatterRegistry;
use Spryker\ApiPlatform\Generator\OpenApiOperationBuilder;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group OpenApiOperationBuilderTest
 * Add your own group annotations below this line
 */
class OpenApiOperationBuilderTest extends Unit
{
    protected const string OPERATION_TYPE_PATCH = 'Patch';

    protected ApiUnitTester $tester;

    public function testGivenAnExplicitRequestBodyWhenGeneratingThenItIsMarkedRequired(): void
    {
        // Arrange
        $operation = [
            'openapiContext' => [
                'requestBody' => [
                    'content' => [
                        'application/vnd.api+json' => ['example' => ['data' => ['type' => 'customers']]],
                    ],
                ],
            ],
        ];
        $builder = $this->createBuilder();

        // Act
        $result = $builder->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH);

        // Assert
        $this->assertStringContainsString('required: true', $result);
    }

    public function testGivenNoRequestBodyWhenGeneratingThenNoneIsEmitted(): void
    {
        // Arrange — no formatter is registered, so nothing generates an example either.
        $builder = $this->createBuilder();

        // Act
        $result = $builder->generateOpenApiOperation([], ['openapiContext' => ['summary' => 'Update']], static::OPERATION_TYPE_PATCH);

        // Assert
        $this->assertStringNotContainsString('requestBody', $result);
    }

    protected function createBuilder(): OpenApiOperationBuilder
    {
        return new OpenApiOperationBuilder(new MediaTypeFormatterRegistry([]), []);
    }
}
