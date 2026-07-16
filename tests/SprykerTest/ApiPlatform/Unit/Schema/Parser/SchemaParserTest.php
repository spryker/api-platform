<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Schema\Parser;

use Codeception\Test\Unit;
use SplFileInfo;
use Spryker\ApiPlatform\Exception\ApiSchemaValidationException;
use Spryker\ApiPlatform\Schema\Parser\SchemaParser;
use Spryker\ApiPlatform\Schema\Parser\SchemaParserInterface;
use Spryker\ApiPlatform\Schema\Validation\Mapper\ValidationGroupMapperInterface;
use Spryker\ApiPlatform\Schema\Validator\PreMergeValidatorInterface;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Schema
 * @group Parser
 * @group SchemaParserTest
 * Add your own group annotations below this line
 */
class SchemaParserTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenValidSchemaWhenParsingThenReturnsNormalizedData(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('Customer', $result['name']);
    }

    public function testGivenSchemaWithoutResourceKeyWhenParsingThenThrowsException(): void
    {
        // Arrange
        $parser = $this->createSchemaParser();

        // Expect
        $this->expectException(ApiSchemaValidationException::class);
        $this->expectExceptionMessage('Schema must have a "resource" key');

        // Act
        $parser->parse([], new SplFileInfo(__FILE__));
    }

    public function testGivenSchemaWithOperationsWhenParsingThenNormalizesOperations(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['operations' => [['type' => 'Get'], ['type' => 'Post']]]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('Get', $result['operations']);
    }

    public function testGivenSchemaWithPropertiesWhenParsingThenNormalizesProperties(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['properties' => ['id' => ['type' => 'int']]]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('integer', $result['properties']['id']['type']);
    }

    public function testGivenObjectPropertyWithNestedPropertiesWhenParsingThenNormalizesNestedProperties(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'properties' => [
                    'totals' => [
                        'type' => 'object',
                        'properties' => [
                            'grandTotal' => ['type' => 'int'],
                        ],
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert — nested properties are recursively normalized (int → integer); no objectName is emitted.
        $this->assertEquals('integer', $result['properties']['totals']['properties']['grandTotal']['type']);
        $this->assertArrayNotHasKey('objectName', $result['properties']['totals']);
    }

    public function testGivenArrayPropertyWithTypedObjectItemsWhenParsingThenNormalizesItemProperties(): void
    {
        // Arrange — an object collection: `type: array` whose `items` describe a typed object.
        $rawSchema = [
            'resource' => [
                'properties' => [
                    'calculations' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'amount' => ['type' => 'int'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert — the item shape is normalized the same way (object item type + int → integer on its fields).
        $this->assertEquals('object', $result['properties']['calculations']['items']['type']);
        $this->assertEquals('integer', $result['properties']['calculations']['items']['properties']['amount']['type']);
        $this->assertArrayNotHasKey('objectName', $result['properties']['calculations']);
    }

    public function testGivenPropertyTypeIntWhenParsingThenConvertsToInteger(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['properties' => ['count' => ['type' => 'int']]]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('integer', $result['properties']['count']['type']);
    }

    public function testGivenPyzPathWhenParsingThenDetectsProjectLayer(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $file = new SplFileInfo('/path/to/Pyz/Module/file.yaml');
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, $file);

        // Assert
        $this->assertEquals('project', $result['sourceLayer']);
    }

    public function testGivenSprykerFeaturePathWhenParsingThenDetectsFeatureLayer(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $file = new SplFileInfo('/path/to/SprykerFeature/Module/file.yaml');
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, $file);

        // Assert
        $this->assertEquals('feature', $result['sourceLayer']);
    }

    public function testGivenValidationSchemasWhenParsingThenAddsValidationSourceFilesAsArray(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $file = new SplFileInfo('/path/to/resources/api/backend/customers.resource.yaml');
        $validationSchemas = [
            'backend_customers' => [
                [
                    'schema' => ['post' => ['name' => ['NotBlank']]],
                    'sourceFile' => '/path/to/validation/customers.validation.yaml',
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, $file, $validationSchemas);

        // Assert
        $this->assertArrayHasKey('validationSourceFiles', $result);
        $this->assertIsArray($result['validationSourceFiles']);
        $this->assertCount(1, $result['validationSourceFiles']);
        $this->assertEquals('/path/to/validation/customers.validation.yaml', $result['validationSourceFiles'][0]);
    }

    public function testGivenOperationWithUriTemplateWhenNormalizingThenExtractsUriTemplate(): void
    {
        $rawSchema = [
            'resource' => [
                'name' => 'CustomersAddresses',
                'operations' => [
                    [
                        'type' => 'GetCollection',
                        'uriTemplate' => '/customers/{customerReference}/addresses',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        $this->assertEquals('/customers/{customerReference}/addresses', $result['operations']['GetCollection']['uriTemplate']);
    }

    public function testGivenOperationWithSecurityWhenNormalizingThenExtractsSecurity(): void
    {
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'operations' => [
                    [
                        'type' => 'Get',
                        'security' => 'is_granted(\'ROLE_USER\')',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        $this->assertEquals('is_granted(\'ROLE_USER\')', $result['operations']['Get']['security']);
    }

    public function testGivenOperationWithSecurityMessageWhenNormalizingThenExtractsSecurityMessage(): void
    {
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'operations' => [
                    [
                        'type' => 'Get',
                        'securityMessage' => 'You can only view your own addresses',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        $this->assertEquals('You can only view your own addresses', $result['operations']['Get']['securityMessage']);
    }

    public function testGivenOperationWithDescriptionWhenNormalizingThenExtractsDescription(): void
    {
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'operations' => [
                    [
                        'type' => 'Get',
                        'description' => 'Retrieve customer details',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        $this->assertEquals('Retrieve customer details', $result['operations']['Get']['description']);
    }

    public function testGivenOperationWithAllSecurityFieldsWhenNormalizingThenExtractsAllFields(): void
    {
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'operations' => [
                    [
                        'type' => 'Post',
                        'uriTemplate' => '/customers/{customerReference}/addresses',
                        'security' => 'is_granted(\'ROLE_USER\')',
                        'securityMessage' => 'Access denied',
                        'description' => 'Create address',
                        'securityPostDenormalize' => 'is_granted(\'ROLE_ADMIN\')',
                        'securityPostDenormalizeMessage' => 'Admin only',
                        'securityPostValidation' => 'is_granted(\'ROLE_SUPER_ADMIN\')',
                        'securityPostValidationMessage' => 'Super admin only',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        $this->assertEquals('/customers/{customerReference}/addresses', $result['operations']['Post']['uriTemplate']);
        $this->assertEquals('is_granted(\'ROLE_USER\')', $result['operations']['Post']['security']);
        $this->assertEquals('Access denied', $result['operations']['Post']['securityMessage']);
        $this->assertEquals('Create address', $result['operations']['Post']['description']);
        $this->assertEquals('is_granted(\'ROLE_ADMIN\')', $result['operations']['Post']['securityPostDenormalize']);
        $this->assertEquals('Admin only', $result['operations']['Post']['securityPostDenormalizeMessage']);
        $this->assertEquals('is_granted(\'ROLE_SUPER_ADMIN\')', $result['operations']['Post']['securityPostValidation']);
        $this->assertEquals('Super admin only', $result['operations']['Post']['securityPostValidationMessage']);
    }

    public function testGivenResourceClassTypeWhenNormalizingPropertyTypeThenPreservesCaseSensitivity(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'CustomerAddress',
                'properties' => [
                    'customer' => [
                        'type' => 'CustomersStorefrontResource',
                        'writable' => false,
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('CustomersStorefrontResource', $result['properties']['customer']['type']);
    }

    public function testGivenStandardPhpTypeWhenNormalizingPropertyTypeThenReturnsLowercase(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Test',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'count' => ['type' => 'integer'],
                    'active' => ['type' => 'boolean'],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('string', $result['properties']['name']['type']);
        $this->assertEquals('integer', $result['properties']['count']['type']);
        $this->assertEquals('boolean', $result['properties']['active']['type']);
    }

    public function testGivenPhpTypeAliasWhenNormalizingPropertyTypeThenMapsToStandardType(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Test',
                'properties' => [
                    'count' => ['type' => 'int'],
                    'active' => ['type' => 'bool'],
                    'price' => ['type' => 'float'],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('integer', $result['properties']['count']['type']);
        $this->assertEquals('boolean', $result['properties']['active']['type']);
        $this->assertEquals('number', $result['properties']['price']['type']);
    }

    public function testGivenResourceWithCustomTagsWhenParsingThenUsesCustomTags(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'shortName' => 'customers',
                'tags' => ['Custom', 'API', 'V2'],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals(['Custom', 'API', 'V2'], $result['tags']);
    }

    public function testGivenResourceWithoutCustomTagsWhenParsingThenGeneratesTagsFromShortName(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'shortName' => 'customers-addresses',
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals(['customers-addresses'], $result['tags']);
    }

    public function testGivenOperationWithCustomTagsWhenParsingThenExtractsTags(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'operations' => [
                    [
                        'type' => 'Get',
                        'tags' => ['Public', 'Read'],
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals(['Public', 'Read'], $result['operations']['Get']['tags']);
    }

    public function testGivenIncludesWithoutManualPropertyWhenParsingThenAutoGeneratesProperty(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'includes' => [
                    [
                        'relationshipName' => 'addresses',
                        'targetResource' => 'CustomersAddresses',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertEquals('array', $result['properties']['addresses']['type']);
        $this->assertFalse($result['properties']['addresses']['writable']);
        $this->assertFalse($result['properties']['addresses']['readable']);
        $this->assertFalse($result['properties']['addresses']['required']);
        $this->assertEquals('Related CustomersAddresses resources', $result['properties']['addresses']['description']);
    }

    public function testGivenIncludesWithManualPropertyWhenParsingThenUsesManualProperty(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'includes' => [
                    [
                        'relationshipName' => 'addresses',
                        'targetResource' => 'CustomersAddresses',
                    ],
                ],
                'properties' => [
                    'addresses' => [
                        'type' => 'array',
                        'writable' => true,
                        'readable' => true,
                        'required' => true,
                        'description' => 'Custom description for addresses',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertEquals('array', $result['properties']['addresses']['type']);
        $this->assertTrue($result['properties']['addresses']['writable']);
        $this->assertTrue($result['properties']['addresses']['readable']);
        $this->assertTrue($result['properties']['addresses']['required']);
        $this->assertEquals('Custom description for addresses', $result['properties']['addresses']['description']);
    }

    public function testGivenMultipleIncludesWhenParsingThenAutoGeneratesAllProperties(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'includes' => [
                    [
                        'relationshipName' => 'addresses',
                        'targetResource' => 'CustomersAddresses',
                    ],
                    [
                        'relationshipName' => 'orders',
                        'targetResource' => 'Orders',
                    ],
                    [
                        'relationshipName' => 'wishlists',
                        'targetResource' => 'Wishlists',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertEquals('Related CustomersAddresses resources', $result['properties']['addresses']['description']);
        $this->assertArrayHasKey('orders', $result['properties']);
        $this->assertEquals('Related Orders resources', $result['properties']['orders']['description']);
        $this->assertArrayHasKey('wishlists', $result['properties']);
        $this->assertEquals('Related Wishlists resources', $result['properties']['wishlists']['description']);
    }

    public function testGivenNoIncludesWhenParsingThenBehaviorUnchanged(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'properties' => [
                    'firstName' => [
                        'type' => 'string',
                    ],
                    'lastName' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertCount(2, $result['properties']);
        $this->assertArrayHasKey('firstName', $result['properties']);
        $this->assertArrayHasKey('lastName', $result['properties']);
        $this->assertArrayNotHasKey('addresses', $result['properties']);
    }

    public function testGivenPartialManualPropertiesWhenParsingThenAutoGeneratesMissingProperties(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'includes' => [
                    [
                        'relationshipName' => 'addresses',
                        'targetResource' => 'CustomersAddresses',
                    ],
                    [
                        'relationshipName' => 'orders',
                        'targetResource' => 'Orders',
                    ],
                ],
                'properties' => [
                    'addresses' => [
                        'type' => 'array',
                        'description' => 'Manual property for addresses',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertEquals('Manual property for addresses', $result['properties']['addresses']['description']);
        $this->assertArrayHasKey('orders', $result['properties']);
        $this->assertEquals('Related Orders resources', $result['properties']['orders']['description']);
        $this->assertFalse($result['properties']['orders']['writable']);
        $this->assertFalse($result['properties']['orders']['readable']);
    }

    public function testGivenIncludesWithUriTemplateWhenParsingThenAutoGeneratesPropertyWithUriTemplate(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'includes' => [
                    [
                        'relationshipName' => 'addresses',
                        'targetResource' => 'CustomersAddresses',
                        'uriTemplate' => '/customers/{customerReference}/addresses',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertArrayHasKey('uriTemplate', $result['properties']['addresses']);
        $this->assertEquals('/customers/{customerReference}/addresses', $result['properties']['addresses']['uriTemplate']);
        $this->assertFalse($result['properties']['addresses']['writable']);
        $this->assertFalse($result['properties']['addresses']['readable']);
    }

    public function testGivenPropertyWithUriTemplateWhenParsingThenPreservesUriTemplate(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'properties' => [
                    'addresses' => [
                        'type' => 'array',
                        'description' => 'Customer addresses',
                        'uriTemplate' => '/customers/{customerReference}/addresses',
                        'writable' => false,
                        'readable' => true,
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertEquals('/customers/{customerReference}/addresses', $result['properties']['addresses']['uriTemplate']);
        $this->assertEquals('Customer addresses', $result['properties']['addresses']['description']);
    }

    public function testGivenManualPropertyWithoutUriTemplateAndIncludeWithUriTemplateWhenParsingThenAddsUriTemplateFromInclude(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'includes' => [
                    [
                        'relationshipName' => 'addresses',
                        'targetResource' => 'CustomersAddresses',
                        'uriTemplate' => '/customers/{customerReference}/addresses',
                    ],
                ],
                'properties' => [
                    'addresses' => [
                        'type' => 'array',
                        'description' => 'Manual addresses without uriTemplate',
                        'writable' => true,
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertEquals('Manual addresses without uriTemplate', $result['properties']['addresses']['description']);
        $this->assertTrue($result['properties']['addresses']['writable']);
        $this->assertArrayHasKey('uriTemplate', $result['properties']['addresses']);
        $this->assertEquals('/customers/{customerReference}/addresses', $result['properties']['addresses']['uriTemplate']);
    }

    public function testGivenManualPropertyWithUriTemplateAndIncludeWithDifferentUriTemplateWhenParsingThenPreservesManualUriTemplate(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'includes' => [
                    [
                        'relationshipName' => 'addresses',
                        'targetResource' => 'CustomersAddresses',
                        'uriTemplate' => '/customers/{customerReference}/addresses',
                    ],
                ],
                'properties' => [
                    'addresses' => [
                        'type' => 'array',
                        'description' => 'Manual addresses with custom uriTemplate',
                        'uriTemplate' => '/custom/path/to/addresses',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertEquals('Manual addresses with custom uriTemplate', $result['properties']['addresses']['description']);
        $this->assertEquals('/custom/path/to/addresses', $result['properties']['addresses']['uriTemplate']);
    }

    public function testGivenIncludesWithoutUriTemplateWhenParsingThenAutoGeneratesPropertyWithoutUriTemplate(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'includes' => [
                    [
                        'relationshipName' => 'addresses',
                        'targetResource' => 'CustomersAddresses',
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('addresses', $result['properties']);
        $this->assertArrayNotHasKey('uriTemplate', $result['properties']['addresses']);
        $this->assertEquals('Related CustomersAddresses resources', $result['properties']['addresses']['description']);
    }

    public function testGivenOperationWithNormalizationContextWhenParsingThenExtractsNormalizationContext(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Tokens',
                'operations' => [
                    [
                        'type' => 'Post',
                        'normalizationContext' => ['gen_id' => false],
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayHasKey('normalizationContext', $result['operations']['Post']);
        $this->assertFalse($result['operations']['Post']['normalizationContext']['gen_id']);
    }

    public function testGivenOperationWithoutOutputWhenParsingThenDoesNotIncludeOutput(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'operations' => [
                    ['type' => 'Get'],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertArrayNotHasKey('output', $result['operations']['Get']);
    }

    public function testGivenSingleValidationSchemaWhenParsingThenValidationIsAlwaysIndexedArray(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $file = new SplFileInfo('/path/to/resources/api/backend/customers.resource.yaml');
        $validationSchemas = [
            'backend_customers' => [
                [
                    'schema' => ['post' => ['name' => ['NotBlank']]],
                    'sourceFile' => '/path/to/validation/customers.validation.yaml',
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, $file, $validationSchemas);

        // Assert
        $this->assertArrayHasKey('validation', $result);
        $this->assertArrayHasKey(0, $result['validation']);
        $this->assertArrayHasKey('post', $result['validation'][0]);
    }

    public function testGivenMultipleValidationSchemasWhenParsingThenValidationIsIndexedArray(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer']];
        $file = new SplFileInfo('/path/to/resources/api/backend/customers.resource.yaml');
        $validationSchemas = [
            'backend_customers' => [
                [
                    'schema' => ['post' => ['name' => ['NotBlank']]],
                    'sourceFile' => '/path/to/first.validation.yaml',
                ],
                [
                    'schema' => ['patch' => ['name' => ['NotBlank']]],
                    'sourceFile' => '/path/to/second.validation.yaml',
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, $file, $validationSchemas);

        // Assert
        $this->assertArrayHasKey('validation', $result);
        $this->assertArrayHasKey(0, $result['validation']);
        $this->assertArrayHasKey(1, $result['validation']);
        $this->assertArrayHasKey('post', $result['validation'][0]);
        $this->assertArrayHasKey('patch', $result['validation'][1]);
    }

    public function testGivenSingleValidationSchemaWithOperationsWhenParsingThenEnrichesValidationGroups(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'shortName' => 'customers',
                'operations' => [
                    ['type' => 'Post'],
                ],
            ],
        ];
        $file = new SplFileInfo('/path/to/resources/api/backend/customers.resource.yaml');
        $validationSchemas = [
            'backend_customers' => [
                [
                    'schema' => ['post' => ['name' => ['NotBlank']]],
                    'sourceFile' => '/path/to/validation/customers.validation.yaml',
                ],
            ],
        ];
        $parser = $this->createSchemaParser('customers_post');

        // Act
        $result = $parser->parse($rawSchema, $file, $validationSchemas);

        // Assert
        $this->assertArrayHasKey('validationGroups', $result['operations']['Post']);
        $this->assertEquals(['customers_post'], $result['operations']['Post']['validationGroups']);
    }

    public function testGivenMultipleValidationSchemasWithOperationsWhenParsingThenEnrichesValidationGroups(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'shortName' => 'customers',
                'operations' => [
                    ['type' => 'Post'],
                    ['type' => 'Patch'],
                ],
            ],
        ];
        $file = new SplFileInfo('/path/to/resources/api/backend/customers.resource.yaml');
        $validationSchemas = [
            'backend_customers' => [
                [
                    'schema' => ['post' => ['name' => ['NotBlank']]],
                    'sourceFile' => '/path/to/first.validation.yaml',
                ],
                [
                    'schema' => ['patch' => ['email' => ['Email']]],
                    'sourceFile' => '/path/to/second.validation.yaml',
                ],
            ],
        ];
        $parser = $this->createSchemaParser('customers_default');

        // Act
        $result = $parser->parse($rawSchema, $file, $validationSchemas);

        // Assert
        $this->assertArrayHasKey('validationGroups', $result['operations']['Post']);
        $this->assertArrayHasKey('validationGroups', $result['operations']['Patch']);
    }

    public function testGivenOperationWithExistingValidationGroupsWhenParsingThenDoesNotOverride(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'shortName' => 'customers',
                'operations' => [
                    ['type' => 'Post', 'validationGroups' => ['custom_group']],
                ],
            ],
        ];
        $file = new SplFileInfo('/path/to/resources/api/backend/customers.resource.yaml');
        $validationSchemas = [
            'backend_customers' => [
                [
                    'schema' => ['post' => ['name' => ['NotBlank']]],
                    'sourceFile' => '/path/to/validation/customers.validation.yaml',
                ],
            ],
        ];
        $parser = $this->createSchemaParser('customers_post');

        // Act
        $result = $parser->parse($rawSchema, $file, $validationSchemas);

        // Assert
        $this->assertEquals(['custom_group'], $result['operations']['Post']['validationGroups']);
    }

    public function testGivenResourceLevelSecurityFieldsWhenParsingThenExtractsAllSecurityFields(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Customer',
                'security' => "is_granted('ROLE_USER')",
                'securityMessage' => 'Access denied',
                'securityPostDenormalize' => "is_granted('ROLE_ADMIN')",
                'securityPostDenormalizeMessage' => 'Admin only',
                'securityPostValidation' => "is_granted('ROLE_SUPER_ADMIN')",
                'securityPostValidationMessage' => 'Super admin only',
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals("is_granted('ROLE_USER')", $result['security']);
        $this->assertEquals('Access denied', $result['securityMessage']);
        $this->assertEquals("is_granted('ROLE_ADMIN')", $result['securityPostDenormalize']);
        $this->assertEquals('Admin only', $result['securityPostDenormalizeMessage']);
        $this->assertEquals("is_granted('ROLE_SUPER_ADMIN')", $result['securityPostValidation']);
        $this->assertEquals('Super admin only', $result['securityPostValidationMessage']);
    }

    public function testGivenPaginationEnabledWhenParsingThenExtractsPaginationEnabled(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer', 'paginationEnabled' => true]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertTrue($result['paginationEnabled']);
    }

    public function testGivenPaginationMaximumItemsPerPageWhenParsingThenExtractsPaginationMaximumItemsPerPage(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer', 'paginationMaximumItemsPerPage' => 100]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals(100, $result['paginationMaximumItemsPerPage']);
    }

    public function testGivenPaginationClientEnabledWhenParsingThenExtractsPaginationClientEnabled(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer', 'paginationClientEnabled' => true]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertTrue($result['paginationClientEnabled']);
    }

    public function testGivenPaginationClientItemsPerPageWhenParsingThenExtractsPaginationClientItemsPerPage(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer', 'paginationClientItemsPerPage' => true]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertTrue($result['paginationClientItemsPerPage']);
    }

    public function testGivenPropertyWithDefaultWhenParsingThenExtractsDefault(): void
    {
        // Arrange
        $rawSchema = ['resource' => ['name' => 'Customer', 'properties' => ['status' => ['type' => 'string', 'default' => 'active']]]];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertEquals('active', $result['properties']['status']['default']);
    }

    public function testGivenPropertyWithNestedPropertiesWhenParsingThenCapturesNestedProperties(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Carts',
                'properties' => [
                    'totals' => [
                        'type' => 'object',
                        'properties' => [
                            'grandTotal' => ['type' => 'int', 'description' => 'Final total'],
                        ],
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertSame('object', $result['properties']['totals']['type']);
        $this->assertArrayHasKey('properties', $result['properties']['totals']);
        $this->assertSame('integer', $result['properties']['totals']['properties']['grandTotal']['type']);
        $this->assertSame('Final total', $result['properties']['totals']['properties']['grandTotal']['description']);
    }

    public function testGivenNestedObjectWithinObjectWhenParsingThenRecursesAllLevels(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Carts',
                'properties' => [
                    'totals' => [
                        'type' => 'object',
                        'properties' => [
                            'tax' => [
                                'type' => 'object',
                                'properties' => ['amount' => ['type' => 'int']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert
        $this->assertSame(
            'integer',
            $result['properties']['totals']['properties']['tax']['properties']['amount']['type'],
        );
    }

    public function testGivenObjectPropertyWithObjectNameWhenParsingThenCarriesObjectNameThrough(): void
    {
        // Arrange
        $rawSchema = [
            'resource' => [
                'name' => 'Checkout',
                'properties' => [
                    'billingAddress' => [
                        'type' => 'object',
                        'objectName' => 'Address',
                        'properties' => [
                            'zipCode' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
        $parser = $this->createSchemaParser();

        // Act
        $result = $parser->parse($rawSchema, new SplFileInfo(__FILE__));

        // Assert — objectName is carried through untouched as the dormant join key for canonical objects.
        $this->assertSame('Address', $result['properties']['billingAddress']['objectName']);
    }

    protected function createSchemaParser(string $validationGroupReturnValue = ''): SchemaParser
    {
        $preMergeValidator = $this->makeEmpty(PreMergeValidatorInterface::class);

        $validationGroupMapper = $validationGroupReturnValue !== ''
            ? $this->makeEmpty(ValidationGroupMapperInterface::class, [
                'mapOperationToGroup' => $validationGroupReturnValue,
            ])
            : $this->makeEmpty(ValidationGroupMapperInterface::class);

        $this->tester->getContainer()->set(PreMergeValidatorInterface::class, $preMergeValidator);
        $this->tester->getContainer()->set(ValidationGroupMapperInterface::class, $validationGroupMapper);

        return $this->tester->getContainer()->get(SchemaParserInterface::class);
    }
}
