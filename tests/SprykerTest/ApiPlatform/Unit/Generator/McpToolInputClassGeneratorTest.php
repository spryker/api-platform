<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\McpToolInputClassGenerator;
use Spryker\ApiPlatform\Generator\NestedObjectClassGenerator;
use Spryker\ApiPlatform\Generator\PropertyAttributeGenerator;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use Spryker\ApiPlatform\Schema\McpToolSchemaResolver;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group McpToolInputClassGeneratorTest
 * Add your own group annotations below this line
 */
class McpToolInputClassGeneratorTest extends Unit
{
    public function testGivenCollectionOperationWithQueryParametersWhenGeneratingThenTheInputClassHasOneTypedPropertyForEachQueryParameter(): void
    {
        // Arrange
        $schema = [
            'name' => 'HealthCheck',
            'shortName' => 'health-check',
            'operations' => [
                'GetCollection' => [
                    'type' => 'GetCollection',
                    'mcp' => ['name' => 'health-check'],
                    'openapiContext' => [
                        'parameters' => [
                            ['name' => 'services', 'in' => 'query', 'required' => false, 'description' => 'Service names.', 'schema' => ['type' => 'string'], 'example' => 'storage'],
                            ['name' => 'limit', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
                            ['name' => 'X-Store', 'in' => 'header', 'schema' => ['type' => 'string']],
                            ['name' => 'mode', 'in' => 'query', 'schema' => ['type' => 'object']],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $result = $this->createGenerator()->generate($schema, 'Storefront', 'HealthCheck', ['health-check.resource.yml']);

        // Assert
        $this->assertSame(['health-check' => 'Generated\Api\Storefront\HealthCheck\HealthCheckMcpToolInput'], $result[McpToolInputClassGenerator::INPUT_CLASSES]);
        $this->assertSame(['HealthCheckMcpToolInput'], array_keys($result[McpToolInputClassGenerator::CLASSES]));

        $code = $result[McpToolInputClassGenerator::CLASSES]['HealthCheckMcpToolInput'];
        $this->assertStringContainsString('namespace Generated\Api\Storefront\HealthCheck;', $code);
        $this->assertStringContainsString("#[ApiProperty(description: 'Service names.', openapiContext: ['example' => 'storage'])]", $code);
        $this->assertStringContainsString('public ?string $services = null;', $code);
        $this->assertStringContainsString('#[ApiProperty(required: true)]', $code);
        $this->assertStringContainsString('public ?int $limit = null;', $code);
        $this->assertStringContainsString('public ?string $mode = null;', $code);
        $this->assertStringNotContainsString('X-Store', $code);
    }

    public function testGivenBracketedQueryParametersWhenGeneratingThenTheInputClassHasCamelCaseProperties(): void
    {
        // Arrange
        $schema = [
            'name' => 'Merchants',
            'shortName' => 'merchants',
            'operations' => [
                'GetCollection' => [
                    'type' => 'GetCollection',
                    'mcp' => true,
                    'openapiContext' => [
                        'parameters' => [
                            ['name' => 'page[limit]', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'filter[company-roles][]', 'in' => 'query', 'schema' => ['type' => 'array']],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $result = $this->createGenerator()->generate($schema, 'Storefront', 'Merchants', ['merchants.resource.yml']);

        // Assert
        $code = $result[McpToolInputClassGenerator::CLASSES]['MerchantsGetCollectionMcpToolInput'];
        $this->assertStringContainsString('public ?int $pageLimit = null;', $code);
        $this->assertStringContainsString('public ?array $filterCompanyRoles = null;', $code);
        $this->assertStringNotContainsString('[limit]', $code);
    }

    public function testGivenItemOperationWithUriVariableWhenGeneratingThenTheUriVariableIsARequiredProperty(): void
    {
        // Arrange
        $schema = [
            'name' => 'Stores',
            'shortName' => 'stores',
            'operations' => [
                'Get' => ['type' => 'Get', 'uriTemplate' => '/stores/{storeName}', 'mcp' => true],
                'GetCollection' => ['type' => 'GetCollection'],
            ],
        ];

        // Act
        $result = $this->createGenerator()->generate($schema, 'Storefront', 'Stores', ['stores.resource.yml']);

        // Assert
        $this->assertSame(['stores-get' => 'Generated\Api\Storefront\Stores\StoresGetMcpToolInput'], $result[McpToolInputClassGenerator::INPUT_CLASSES]);
        $code = $result[McpToolInputClassGenerator::CLASSES]['StoresGetMcpToolInput'];
        $this->assertStringContainsString('#[ApiProperty(required: true)]', $code);
        $this->assertStringContainsString('public ?string $storeName = null;', $code);
    }

    public function testGivenItemOperationWithoutUriTemplateWhenGeneratingThenTheIdentifierIsARequiredProperty(): void
    {
        // Arrange
        $schema = [
            'name' => 'Merchants',
            'shortName' => 'merchants',
            'properties' => [
                'merchantReference' => ['type' => 'string', 'identifier' => true],
                'name' => ['type' => 'string'],
            ],
            'operations' => ['Get' => ['type' => 'Get', 'mcp' => true]],
        ];

        // Act
        $result = $this->createGenerator()->generate($schema, 'Storefront', 'Merchants', ['merchants.resource.yml']);

        // Assert
        $code = $result[McpToolInputClassGenerator::CLASSES]['MerchantsGetMcpToolInput'];
        $this->assertStringContainsString('#[ApiProperty(required: true)]', $code);
        $this->assertStringContainsString('public ?string $merchantReference = null;', $code);
        $this->assertStringNotContainsString('$name', $code);
    }

    public function testGivenResourceWithoutToolWhenGeneratingThenNoClassIsGenerated(): void
    {
        // Arrange
        $schema = ['name' => 'Stores', 'shortName' => 'stores', 'operations' => ['Get' => ['type' => 'Get']]];

        // Act
        $result = $this->createGenerator()->generate($schema, 'Storefront', 'Stores', ['stores.resource.yml']);

        // Assert
        $this->assertSame([McpToolInputClassGenerator::INPUT_CLASSES => [], McpToolInputClassGenerator::CLASSES => []], $result);
    }

    protected function createGenerator(): McpToolInputClassGenerator
    {
        return new McpToolInputClassGenerator(
            new NestedObjectClassGenerator(new PropertyAttributeGenerator(), new PhpTemplateRenderer()),
            new McpToolSchemaResolver(),
        );
    }
}
