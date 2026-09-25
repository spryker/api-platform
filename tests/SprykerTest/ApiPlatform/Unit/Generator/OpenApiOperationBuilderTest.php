<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Exception\ApiSchemaGenerationException;
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

    protected const string OPERATION_TYPE_POST = 'Post';

    protected ApiUnitTester $tester;

    public function testGivenOperationDeclaresOpenapiContextResponsesWhenGeneratingThenResponsesAppearInOpenApiOperation(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'uriTemplate' => '/customer-password/{customerReference}',
            'openapiContext' => [
                'responses' => [
                    204 => ['description' => 'Password updated successfully.'],
                    403 => ['description' => 'Access denied.'],
                ],
            ],
        ];

        // Act
        $result = $this->createBuilder()->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH);

        // Assert
        $this->assertStringContainsString("204 => new Response(description: 'Password updated successfully.')", $result);
        $this->assertStringContainsString("403 => new Response(description: 'Access denied.')", $result);
    }

    public function testGivenOperationDeclaresResponsesAndTagsWhenGeneratingThenBothAppearInOpenApiOperation(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'openapiContext' => [
                'responses' => [
                    204 => ['description' => 'Updated.'],
                ],
            ],
        ];

        // Act
        $result = $this->createBuilder()
            ->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH, ['customer-password']);

        // Assert
        $this->assertStringContainsString("tags: ['customer-password']", $result);
        $this->assertStringContainsString("204 => new Response(description: 'Updated.')", $result);
    }

    public function testGivenOperationDeclaresResponsesAndRequestBodyWhenGeneratingThenResponsesSurviveTheRequestBodyPath(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_POST,
            'openapiContext' => [
                'responses' => [
                    201 => ['description' => 'Created.'],
                    422 => ['description' => 'Validation failed.'],
                ],
                'requestBody' => [
                    'content' => [
                        'application/vnd.api+json' => ['example' => ['data' => []]],
                    ],
                ],
            ],
        ];

        // Act
        $result = $this->createBuilder()->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_POST);

        // Assert
        $this->assertStringContainsString("201 => new Response(description: 'Created.')", $result);
        $this->assertStringContainsString("422 => new Response(description: 'Validation failed.')", $result);
        $this->assertStringContainsString('requestBody: new RequestBody(', $result);
    }

    public function testGivenResponseDescriptionContainsSingleQuoteWhenGeneratingThenItIsEscaped(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'openapiContext' => [
                'responses' => [
                    403 => ['description' => "Customer's reference does not match."],
                ],
            ],
        ];

        // Act
        $result = $this->createBuilder()->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH);

        // Assert
        $this->assertStringContainsString(
            "403 => new Response(description: 'Customer\\'s reference does not match.')",
            $result,
        );
    }

    public function testGivenOperationDeclaresNoResponsesWhenGeneratingThenNoResponsesParameterIsEmitted(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'openapiContext' => [
                'summary' => 'Change customer password',
            ],
        ];

        // Act
        $result = $this->createBuilder()
            ->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH, ['customer-password']);

        // Assert
        $this->assertStringNotContainsString('responses:', $result);
    }

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

    public function testGivenOperationDeclaresOpenapiContextParametersWhenGeneratingThenTheyAppearInOpenApiOperation(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'openapiContext' => [
                'parameters' => [
                    [
                        'name' => 'q',
                        'in' => 'query',
                        'description' => 'Free-text search query.',
                        'required' => false,
                        'schema' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        // Act
        $result = $this->createBuilder()->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH);

        // Assert
        $this->assertStringContainsString("parameters: [\n", $result);
        $this->assertStringContainsString(
            "new Parameter(name: 'q', in: 'query', description: 'Free-text search query.', required: false, schema: ['type' => 'string']),",
            $result,
        );
    }

    public function testGivenAQueryParameterDeclaringAnExampleWhenGeneratingThenTheExampleIsCarriedThrough(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'openapiContext' => [
                'parameters' => [
                    [
                        'name' => 'page[limit]',
                        'in' => 'query',
                        'required' => false,
                        'schema' => ['type' => 'integer'],
                        'example' => 10,
                    ],
                ],
            ],
        ];

        // Act
        $result = $this->createBuilder()->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH);

        // Assert
        $this->assertStringContainsString("parameters: [\n", $result);
        $this->assertStringContainsString(
            "new Parameter(name: 'page[limit]', in: 'query', required: false, schema: ['type' => 'integer'], example: 10),",
            $result,
        );
    }

    public function testGivenOperationDeclaresNoParametersWhenGeneratingThenNoParametersParameterIsEmitted(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'openapiContext' => [
                'responses' => [
                    204 => ['description' => 'Updated.'],
                ],
            ],
        ];

        // Act
        $result = $this->createBuilder()->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH);

        // Assert
        $this->assertStringNotContainsString('parameters:', $result);
    }

    public function testGivenAQueryParameterWithoutANameWhenGeneratingThenGenerationFailsInsteadOfDroppingIt(): void
    {
        // Arrange
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'openapiContext' => [
                'parameters' => [
                    ['in' => 'query', 'schema' => ['type' => 'string']],
                ],
            ],
        ];

        // Assert
        $this->expectException(ApiSchemaGenerationException::class);

        // Act
        $this->createBuilder()->generateOpenApiOperation([], $operation, static::OPERATION_TYPE_PATCH);
    }

    public function testGivenANamelessQueryParameterWhenGeneratingThenTheMessageNamesTheDeclarationToChange(): void
    {
        // Arrange
        $parsedSchema = [
            'shortName' => 'customer-password',
            'sourceFile' => 'src/Spryker/CustomersRestApi/resources/api/storefront/customer-password.resource.yml',
        ];
        $operation = [
            'type' => static::OPERATION_TYPE_PATCH,
            'uriTemplate' => '/customer-password',
            'openapiContext' => [
                'parameters' => [
                    ['in' => 'query', 'schema' => ['type' => 'string']],
                ],
            ],
        ];

        // Assert
        $this->expectException(ApiSchemaGenerationException::class);
        $this->expectExceptionMessage(
            'openapiContext.parameters entry #0 of customer-password Patch /customer-password '
                . '(src/Spryker/CustomersRestApi/resources/api/storefront/customer-password.resource.yml) '
                . 'declares no "name"',
        );

        // Act
        $this->createBuilder()->generateOpenApiOperation($parsedSchema, $operation, static::OPERATION_TYPE_PATCH);
    }

    protected function createBuilder(): OpenApiOperationBuilder
    {
        return new OpenApiOperationBuilder(new MediaTypeFormatterRegistry([]), []);
    }
}
