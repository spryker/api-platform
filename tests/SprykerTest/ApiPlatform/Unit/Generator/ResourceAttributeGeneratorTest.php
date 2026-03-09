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
        $this->assertStringContainsString('new Get(', $result);
        $this->assertStringContainsString('openapi: new Operation(', $result);
        $this->assertStringContainsString("tags: ['User Management', 'V2']", $result);
        $this->assertContains('ApiPlatform\OpenApi\Model\Operation', $uses);
    }

    public function testGivenOperationWithNormalizationContextGenIdFalseWhenGeneratingThenIncludesNormalizationContextParameter(): void
    {
        // Arrange
        $schema = [
            'name' => 'Tokens',
            'shortName' => 'tokens',
            'operations' => [
                'Post' => [
                    'type' => 'Post',
                    'normalizationContext' => ['gen_id' => false],
                ],
            ],
        ];
        $uses = [];
        $generator = $this->createResourceAttributeGenerator();

        // Act
        $result = $generator->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString("normalizationContext: ['gen_id' => false]", $result);
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

    public function testGivenPaginationEnabledWhenGeneratingThenIncludesPaginationEnabled(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'shortName' => 'customers', 'paginationEnabled' => true, 'operations' => []];
        $uses = [];

        // Act
        $result = $this->createResourceAttributeGenerator()->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString('paginationEnabled: true', $result);
    }

    public function testGivenPaginationMaximumItemsPerPageWhenGeneratingThenIncludesPaginationMaximumItemsPerPage(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'shortName' => 'customers', 'paginationMaximumItemsPerPage' => 100, 'operations' => []];
        $uses = [];

        // Act
        $result = $this->createResourceAttributeGenerator()->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString('paginationMaximumItemsPerPage: 100', $result);
    }

    public function testGivenPaginationClientEnabledWhenGeneratingThenIncludesPaginationClientEnabled(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'shortName' => 'customers', 'paginationClientEnabled' => true, 'operations' => []];
        $uses = [];

        // Act
        $result = $this->createResourceAttributeGenerator()->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString('paginationClientEnabled: true', $result);
    }

    public function testGivenPaginationClientItemsPerPageWhenGeneratingThenIncludesPaginationClientItemsPerPage(): void
    {
        // Arrange
        $schema = ['name' => 'Customer', 'shortName' => 'customers', 'paginationClientItemsPerPage' => true, 'operations' => []];
        $uses = [];

        // Act
        $result = $this->createResourceAttributeGenerator()->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString('paginationClientItemsPerPage: true', $result);
    }

    public function testGivenResourceLevelSecurityWhenGeneratingThenIncludesSecurityInApiResourceAttribute(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'shortName' => 'customers',
            'security' => "is_granted('ROLE_USER')",
            'operations' => [],
        ];
        $uses = [];

        // Act
        $result = $this->createResourceAttributeGenerator()->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString("security: 'is_granted(\\'ROLE_USER\\')'", $result);
    }

    public function testGivenResourceLevelSecurityFieldsWhenGeneratingThenIncludesAllSecurityFieldsInApiResourceAttribute(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'shortName' => 'customers',
            'security' => "is_granted('ROLE_USER')",
            'securityMessage' => 'Access denied',
            'securityPostDenormalize' => "is_granted('ROLE_ADMIN')",
            'securityPostDenormalizeMessage' => 'Admin only',
            'securityPostValidation' => "is_granted('ROLE_SUPER_ADMIN')",
            'securityPostValidationMessage' => 'Super admin only',
            'operations' => [],
        ];
        $uses = [];

        // Act
        $result = $this->createResourceAttributeGenerator()->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString("security: 'is_granted(\\'ROLE_USER\\')'", $result);
        $this->assertStringContainsString("securityMessage: 'Access denied'", $result);
        $this->assertStringContainsString("securityPostDenormalize: 'is_granted(\\'ROLE_ADMIN\\')'", $result);
        $this->assertStringContainsString("securityPostDenormalizeMessage: 'Admin only'", $result);
        $this->assertStringContainsString("securityPostValidation: 'is_granted(\\'ROLE_SUPER_ADMIN\\')'", $result);
        $this->assertStringContainsString("securityPostValidationMessage: 'Super admin only'", $result);
    }

    public function testGivenResourceLevelOpenapiContextWhenGeneratingThenIncludesOpenapiContext(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'shortName' => 'customers',
            'openapiContext' => ['summary' => 'Customer resource'],
            'operations' => [],
        ];
        $uses = [];

        // Act
        $result = $this->createResourceAttributeGenerator()->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString("openapiContext: ['summary' => 'Customer resource']", $result);
    }

    public function testGivenOperationLevelProcessorWhenGeneratingThenIncludesProcessorInOperation(): void
    {
        // Arrange
        $schema = [
            'name' => 'Customer',
            'shortName' => 'customers',
            'operations' => [
                'Post' => [
                    'type' => 'Post',
                    'processor' => 'Spryker\Glue\Customer\Api\Processor\CustomersProcessor',
                ],
            ],
        ];
        $uses = [];

        // Act
        $result = $this->createResourceAttributeGenerator()->generate($schema, $uses);

        // Assert
        $this->assertStringContainsString('processor: CustomersProcessor::class', $result);
        $this->assertContains('Spryker\Glue\Customer\Api\Processor\CustomersProcessor', $uses);
    }

    protected function createResourceAttributeGenerator(): ResourceAttributeGenerator
    {
        $openApiOperationBuilder = $this->createMock(OpenApiOperationBuilder::class);
        $openApiOperationBuilder->method('generateOpenApiOperation')->willReturn('');

        $this->tester->getContainer()->set(OpenApiOperationBuilder::class, $openApiOperationBuilder);

        return $this->tester->getContainer()->get(ResourceAttributeGenerator::class);
    }
}
