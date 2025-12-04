<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Generator\Template;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Generator\Template\PhpTemplateRenderer;
use SprykerTest\ApiPlatform\ApiUnitTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Generator
 * @group Template
 * @group PhpTemplateRendererTest
 * Add your own group annotations below this line
 */
class PhpTemplateRendererTest extends Unit
{
    protected ApiUnitTester $tester;

    public function testGivenTemplateDataWhenRenderingThenReturnsValidPhp(): void
    {
        // Arrange
        $templateData = $this->createMinimalTemplateData();
        $renderer = new PhpTemplateRenderer();

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('<?php', $result);
    }

    public function testGivenClassNameWhenRenderingThenIncludesClassName(): void
    {
        // Arrange
        $templateData = $this->createMinimalTemplateData();
        $renderer = new PhpTemplateRenderer();

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('class CustomerResource', $result);
    }

    public function testGivenNamespaceWhenRenderingThenIncludesNamespace(): void
    {
        // Arrange
        $templateData = $this->createMinimalTemplateData();
        $renderer = new PhpTemplateRenderer();

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('namespace Generated\Api;', $result);
    }

    public function testGivenPropertiesWhenRenderingThenIncludesProperties(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithProperties();
        $renderer = new PhpTemplateRenderer();

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('public ?int $id = null;', $result);
    }

    public function testGivenPropertiesWhenRenderingThenIncludesGetters(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithProperties();
        $renderer = new PhpTemplateRenderer();

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('public function getId()', $result);
    }

    public function testGivenPropertiesWhenRenderingThenIncludesSetters(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithProperties();
        $renderer = new PhpTemplateRenderer();

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('public function setId(?int $id)', $result);
    }

    public function testGivenResourceAttributeWhenRenderingThenIncludesAttribute(): void
    {
        // Arrange
        $templateData = $this->createMinimalTemplateData();
        $renderer = new PhpTemplateRenderer();

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $this->assertStringContainsString('#[ApiResource]', $result);
    }

    public function testGivenMultiplePropertiesWhenRenderingThenGetterAndSetterAreGroupedPerProperty(): void
    {
        // Arrange
        $templateData = $this->createTemplateDataWithMultipleProperties();
        $renderer = new PhpTemplateRenderer();

        // Act
        $result = $renderer->render($templateData);

        // Assert
        $getIdPosition = strpos($result, 'public function getId()');
        $setIdPosition = strpos($result, 'public function setId(');
        $getNamePosition = strpos($result, 'public function getName()');
        $setNamePosition = strpos($result, 'public function setName(');

        $this->assertNotFalse($getIdPosition);
        $this->assertNotFalse($setIdPosition);
        $this->assertNotFalse($getNamePosition);
        $this->assertNotFalse($setNamePosition);
        $this->assertLessThan($getIdPosition, $setIdPosition, 'setId should come before getId');
        $this->assertLessThan($setNamePosition, $getIdPosition, 'getId should come before setName');
        $this->assertLessThan($getNamePosition, $setNamePosition, 'setName should come before getName');
    }

    /**
     * @return array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, properties: array<mixed>, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}
     */
    protected function createMinimalTemplateData(): array
    {
        return [
            'className' => 'CustomerResource',
            'namespace' => 'Generated\Api',
            'uses' => [],
            'resourceAttribute' => '#[ApiResource]',
            'properties' => [],
            'metadata' => [
                'timestamp' => '2024-01-01',
                'sourceFiles' => ['test.yaml'],
                'validationSourceFiles' => [],
            ],
        ];
    }

    /**
     * @return array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, properties: array<array{name: string, type: string, phpType: string, attributes: string, description: string}>, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}
     */
    protected function createTemplateDataWithProperties(): array
    {
        return [
            'className' => 'CustomerResource',
            'namespace' => 'Generated\Api',
            'uses' => [],
            'resourceAttribute' => '#[ApiResource]',
            'properties' => [
                [
                    'name' => 'id',
                    'type' => 'integer',
                    'phpType' => 'int',
                    'attributes' => '',
                    'description' => 'ID',
                ],
            ],
            'metadata' => [
                'timestamp' => '2024-01-01',
                'sourceFiles' => ['test.yaml'],
                'validationSourceFiles' => [],
            ],
        ];
    }

    /**
     * @return array{className: string, namespace: string, uses: array<string>, resourceAttribute: string, properties: array<array{name: string, type: string, phpType: string, attributes: string, description: string}>, metadata: array{timestamp: string, sourceFiles: array<string>, validationSourceFiles: array<string>}}
     */
    protected function createTemplateDataWithMultipleProperties(): array
    {
        return [
            'className' => 'CustomerResource',
            'namespace' => 'Generated\Api',
            'uses' => [],
            'resourceAttribute' => '#[ApiResource]',
            'properties' => [
                [
                    'name' => 'id',
                    'type' => 'integer',
                    'phpType' => 'int',
                    'attributes' => '',
                    'description' => 'ID',
                ],
                [
                    'name' => 'name',
                    'type' => 'string',
                    'phpType' => 'string',
                    'attributes' => '',
                    'description' => 'Name',
                ],
            ],
            'metadata' => [
                'timestamp' => '2024-01-01',
                'sourceFiles' => ['test.yaml'],
                'validationSourceFiles' => [],
            ],
        ];
    }
}
