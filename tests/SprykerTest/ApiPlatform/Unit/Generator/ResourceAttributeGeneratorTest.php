<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\OpenApiOperationBuilder;
use Spryker\ApiPlatform\Generator\ResourceAttributeGenerator;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group ResourceAttributeGeneratorTest
 * Add your own group annotations below this line
 */
class ResourceAttributeGeneratorTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenOperationWithUriVariablesWhenGeneratingThenIncludesLinkWithParameterName(): void
    {
        // Arrange
        $schema = [
            'name' => 'CustomerAddress',
            'shortName' => 'customer-address',
            'operations' => [
                'GetCollection' => [
                    'type' => 'GetCollection',
                    'uriTemplate' => '/customers/{customerReference}/addresses',
                    'uriVariables' => [
                        'customerReference' => [
                            'toProperty' => 'customer',
                            'fromClass' => 'CustomersStorefrontResource',
                        ],
                    ],
                    'description' => 'Retrieve all addresses for the authenticated customer',
                ],
            ],
        ];
        $uses = [];
        $generator = $this->createResourceAttributeGenerator();

        // Act
        $result = $generator->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString('new GetCollection(', $result);
        $this->assertStringContainsString("uriTemplate: '/customers/{customerReference}/addresses'", $result);
        $this->assertStringContainsString('uriVariables: [', $result);
        $this->assertStringContainsString("'customerReference' => new Link(", $result);
        $this->assertStringContainsString("parameterName: 'customerReference'", $result);
        $this->assertStringContainsString("toProperty: 'customer'", $result);
        $this->assertStringContainsString('fromClass: CustomersStorefrontResource::class', $result);
    }

    public function testGivenOperationWithMultipleUriVariablesWhenGeneratingThenIncludesAllLinks(): void
    {
        // Arrange
        $schema = [
            'name' => 'OrderItem',
            'shortName' => 'order-item',
            'operations' => [
                'Get' => [
                    'type' => 'Get',
                    'uriTemplate' => '/customers/{customerReference}/orders/{orderReference}/items/{itemId}',
                    'uriVariables' => [
                        'customerReference' => [
                            'toProperty' => 'customer',
                            'fromClass' => 'CustomersStorefrontResource',
                        ],
                        'orderReference' => [
                            'toProperty' => 'order',
                            'fromClass' => 'OrdersStorefrontResource',
                        ],
                    ],
                ],
            ],
        ];
        $uses = [];
        $generator = $this->createResourceAttributeGenerator();

        // Act
        $result = $generator->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString("'customerReference' => new Link(", $result);
        $this->assertStringContainsString("parameterName: 'customerReference'", $result);
        $this->assertStringContainsString("'orderReference' => new Link(", $result);
        $this->assertStringContainsString("parameterName: 'orderReference'", $result);
    }

    public function testGivenOperationWithUriVariablesWhenGeneratingThenAddsLinkUseStatement(): void
    {
        // Arrange
        $schema = [
            'name' => 'CustomerAddress',
            'shortName' => 'customer-address',
            'operations' => [
                'Get' => [
                    'type' => 'Get',
                    'uriVariables' => [
                        'customerReference' => [
                            'toProperty' => 'customer',
                            'fromClass' => 'CustomersStorefrontResource',
                        ],
                    ],
                ],
            ],
        ];
        $uses = [];
        $generator = $this->createResourceAttributeGenerator();

        // Act
        $generator->generate($schema, $uses);

        // Assert
        $this->assertContains('ApiPlatform\Metadata\Link', $uses);
    }

    public function testGivenOperationWithFromPropertyWhenGeneratingThenIncludesFromProperty(): void
    {
        // Arrange
        $schema = [
            'name' => 'Test',
            'shortName' => 'test',
            'operations' => [
                'Get' => [
                    'type' => 'Get',
                    'uriVariables' => [
                        'customerId' => [
                            'fromProperty' => 'customerReference',
                            'toProperty' => 'customer',
                            'fromClass' => 'CustomersStorefrontResource',
                        ],
                    ],
                ],
            ],
        ];
        $uses = [];
        $generator = $this->createResourceAttributeGenerator();

        // Act
        $result = $generator->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString("fromProperty: 'customerReference'", $result);
    }

    public function testGivenSchemaWithCustomTagsWhenGeneratingGetOperationThenIncludesTagsInOpenApiOperation(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'shortName' => 'customers',
            'tags' => ['User Management', 'V2'],
            'operations' => [
                'Get' => [],
            ],
        ];
        $uses = [];
        $generator = $this->createResourceAttributeGenerator();

        // Act
        $result = $generator->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString('new Get(openapi: new Operation(tags: ', $result);
        $this->assertContains('ApiPlatform\OpenApi\Model\Operation', $uses);
    }

    public function testGivenOperationWithOutputGenIdFalseWhenGeneratingThenIncludesOutputParameter(): void
    {
        // Arrange
        $schema = [
            'name' => 'Tokens',
            'shortName' => 'tokens',
            'operations' => [
                'Post' => [
                    'type' => 'Post',
                    'output' => ['gen_id' => false],
                ],
            ],
        ];
        $uses = [];
        $generator = $this->createResourceAttributeGenerator();

        // Act
        $result = $generator->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString("output: ['gen_id' => false]", $result);
    }

    public function testGivenOperationWithoutOutputWhenGeneratingThenDoesNotIncludeOutputParameter(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'shortName' => 'customers',
            'operations' => [
                'Get' => [
                    'type' => 'Get',
                ],
            ],
        ];
        $uses = [];
        $generator = $this->createResourceAttributeGenerator();

        // Act
        $result = $generator->generate($schema, $uses);

        // Assert
        $this->assertStringNotContainsString('output:', $result);
    }

    protected function createResourceAttributeGenerator(): ResourceAttributeGenerator
    {
        $openApiOperationBuilder = $this->createMock(OpenApiOperationBuilder::class);
        $openApiOperationBuilder->method('generateOpenApiOperation')->willReturn('');

        $this->tester->getContainer()->set(OpenApiOperationBuilder::class, $openApiOperationBuilder);

        return $this->tester->getContainer()->get(ResourceAttributeGenerator::class);
    }
}
