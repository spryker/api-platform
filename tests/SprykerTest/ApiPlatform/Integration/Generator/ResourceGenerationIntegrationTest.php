<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Integration\Generator;

use Codeception\Test\Unit;
use SplFileInfo;
use Spryker\ApiPlatform\Generator\ClassGenerator;
use Spryker\ApiPlatform\Schema\Parser\SchemaParser;
use Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface;
use SprykerTest\ApiPlatform\ApiIntegrationTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Integration
 * @group Generator
 * @group ResourceGenerationIntegrationTest
 * Add your own group annotations below this line
 */
class ResourceGenerationIntegrationTest extends Unit
{
    protected ApiIntegrationTester $tester;

    public function testGivenCompleteSchemaWithUriVariablesAndResourceTypesWhenGeneratingThenCreatesValidCode(): void
    {
        // Arrange
        $schema = [
            'resource' => [
                'name' => 'CustomersAddresses',
                'shortName' => 'customers-addresses',
                'description' => 'Customer address management',
                'provider' => 'Spryker\Glue\Customer\Api\Storefront\Provider\CustomersAddressesStorefrontProvider',
                'processor' => 'Spryker\Glue\Customer\Api\Storefront\Processor\CustomersAddressesStorefrontProcessor',
                'paginationEnabled' => false,
                'operations' => [
                    [
                        'type' => 'GetCollection',
                        'description' => 'Retrieve all addresses for the authenticated customer',
                        'uriTemplate' => '/customers/{customerReference}/addresses',
                        'uriVariables' => [
                            'customerReference' => [
                                'toProperty' => 'customer',
                                'fromClass' => 'CustomersStorefrontResource',
                            ],
                        ],
                    ],
                    [
                        'type' => 'Get',
                        'description' => 'Retrieve a specific address by UUID',
                        'uriTemplate' => '/customers/{customerReference}/addresses/{uuid}',
                        'uriVariables' => [
                            'customerReference' => [
                                'toProperty' => 'customer',
                                'fromClass' => 'CustomersStorefrontResource',
                            ],
                        ],
                    ],
                ],
                'properties' => [
                    'uuid' => [
                        'type' => 'string',
                        'identifier' => true,
                        'writable' => false,
                        'readable' => true,
                        'required' => false,
                        'description' => 'Unique identifier for the address',
                    ],
                    'customerReference' => [
                        'type' => 'string',
                        'identifier' => true,
                        'writable' => false,
                        'readable' => true,
                        'required' => false,
                        'description' => 'Reference to the customer',
                    ],
                    'customer' => [
                        'type' => 'CustomersStorefrontResource',
                        'writable' => false,
                        'readable' => true,
                        'required' => true,
                        'description' => 'The customer who owns this address',
                    ],
                    'firstName' => [
                        'type' => 'string',
                        'writable' => true,
                        'readable' => true,
                        'required' => true,
                        'description' => 'First name',
                    ],
                    'city' => [
                        'type' => 'string',
                        'writable' => true,
                        'readable' => true,
                        'required' => true,
                        'description' => 'City name',
                    ],
                ],
            ],
        ];

        $schemaParser = $this->createSchemaParser();
        $classGenerator = $this->createClassGenerator();

        // Act
        $parsedSchema = $schemaParser->parse($schema, new SplFileInfo(__FILE__));
        $generatedCode = $classGenerator->generate($parsedSchema, 'Storefront');

        // Assert - Verify schema parsing preserved resource class name case
        $this->assertSame('CustomersAddresses', $parsedSchema['name']);
        $this->assertSame('CustomersStorefrontResource', $parsedSchema['properties']['customer']['type']);

        // Assert - Verify Link objects with parameterName are generated
        $this->assertStringContainsString('new GetCollection(', $generatedCode);
        $this->assertStringContainsString("uriTemplate: '/customers/{customerReference}/addresses'", $generatedCode);
        $this->assertStringContainsString('uriVariables: [', $generatedCode);
        $this->assertStringContainsString("'customerReference' => new Link(", $generatedCode);
        $this->assertStringContainsString("parameterName: 'customerReference'", $generatedCode);
        $this->assertStringContainsString("toProperty: 'customer'", $generatedCode);
        $this->assertStringContainsString('fromClass: CustomersStorefrontResource::class', $generatedCode);

        // Assert - Verify resource type property is properly typed
        $this->assertStringContainsString('public ?CustomersStorefrontResource $customer = null;', $generatedCode);

        // Assert - Verify use statements include resource class
        $this->assertStringContainsString("use ApiPlatform\Metadata\Link;", $generatedCode);

        // Assert - Verify generated code is valid PHP
        $this->assertStringContainsString('declare(strict_types=1);', $generatedCode);
        $this->assertStringContainsString("namespace Generated\Api\Storefront;", $generatedCode);
        $this->assertStringContainsString('final class CustomersAddressesStorefrontResource', $generatedCode);

        // Assert - Verify getters/setters are generated for all properties
        $this->assertStringContainsString('public function getUuid(): ?string', $generatedCode);
        $this->assertStringContainsString('public function setUuid(?string $uuid): self', $generatedCode);
        $this->assertStringContainsString('public function getCustomerReference(): ?string', $generatedCode);
        $this->assertStringContainsString('public function setCustomerReference(?string $customerReference): self', $generatedCode);
        $this->assertStringContainsString('public function getFirstName(): ?string', $generatedCode);
        $this->assertStringContainsString('public function setFirstName(?string $firstName): self', $generatedCode);
        $this->assertStringContainsString('public function getCity(): ?string', $generatedCode);
        $this->assertStringContainsString('public function setCity(?string $city): self', $generatedCode);
    }

    public function testGivenMultipleUriVariablesWhenGeneratingThenIncludesAllLinkParameters(): void
    {
        // Arrange
        $schema = [
            'resource' => [
                'name' => 'OrderItems',
                'shortName' => 'order-items',
                'description' => 'Order item management',
                'operations' => [
                    [
                        'type' => 'Get',
                        'description' => 'Retrieve order item',
                        'uriTemplate' => '/customers/{customerReference}/orders/{orderReference}/items/{id}',
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
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'identifier' => true,
                    ],
                    'customer' => [
                        'type' => 'CustomersStorefrontResource',
                    ],
                    'order' => [
                        'type' => 'OrdersStorefrontResource',
                    ],
                ],
            ],
        ];

        $schemaParser = $this->createSchemaParser();
        $classGenerator = $this->createClassGenerator();

        // Act
        $parsedSchema = $schemaParser->parse($schema, new SplFileInfo(__FILE__));
        $generatedCode = $classGenerator->generate($parsedSchema, 'Storefront');

        // Assert - Verify both uriVariables are included with parameterName
        $this->assertStringContainsString("'customerReference' => new Link(", $generatedCode);
        $this->assertStringContainsString("parameterName: 'customerReference'", $generatedCode);
        $this->assertStringContainsString("toProperty: 'customer'", $generatedCode);
        $this->assertStringContainsString('fromClass: CustomersStorefrontResource::class', $generatedCode);

        $this->assertStringContainsString("'orderReference' => new Link(", $generatedCode);
        $this->assertStringContainsString("parameterName: 'orderReference'", $generatedCode);
        $this->assertStringContainsString("toProperty: 'order'", $generatedCode);
        $this->assertStringContainsString('fromClass: OrdersStorefrontResource::class', $generatedCode);

        // Assert - Verify both resource type properties are properly typed
        $this->assertStringContainsString('public ?CustomersStorefrontResource $customer = null;', $generatedCode);
        $this->assertStringContainsString('public ?OrdersStorefrontResource $order = null;', $generatedCode);
    }

    public function testGivenResourceWithCamelCaseNameWhenParsingThenPreservesCase(): void
    {
        // Arrange
        $schema = [
            'resource' => [
                'name' => 'CustomersAddresses',
                'shortName' => 'customers-addresses',
                'operations' => [],
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'identifier' => true,
                    ],
                ],
            ],
        ];

        $schemaParser = $this->createSchemaParser();

        // Act
        $parsedSchema = $schemaParser->parse($schema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertSame('CustomersAddresses', $parsedSchema['name']);
    }

    public function testGivenResourceTypePropertyWhenGeneratingThenMapsToPhpType(): void
    {
        // Arrange
        $schema = [
            'resource' => [
                'name' => 'Address',
                'shortName' => 'address',
                'operations' => [],
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'identifier' => true,
                    ],
                    'customer' => [
                        'type' => 'CustomersStorefrontResource',
                        'description' => 'Related customer',
                    ],
                ],
            ],
        ];

        $schemaParser = $this->createSchemaParser();
        $classGenerator = $this->createClassGenerator();

        // Act
        $parsedSchema = $schemaParser->parse($schema, new SplFileInfo(__FILE__));
        $generatedCode = $classGenerator->generate($parsedSchema, 'Storefront');

        // Assert
        $this->assertStringContainsString('public ?CustomersStorefrontResource $customer = null;', $generatedCode);
        $this->assertStringNotContainsString('public ?string $customer = null;', $generatedCode);
    }

    protected function createSchemaParser(): SchemaParser
    {
        return $this->tester->getContainer()->get(SchemaParserInterface::class);
    }

    protected function createClassGenerator(): ClassGenerator
    {
        return $this->tester->getContainer()->get(ClassGenerator::class);
    }
}
